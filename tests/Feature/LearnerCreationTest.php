<?php

use App\Actions\AuditLearner;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use App\Support\SmileIdGenerator;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\ActivityLogStatus;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesSeeder::class);
    $this->teacher = User::factory()->create();
    $this->teacher->assignRole(['sped_teacher', 'school_admin']);
    $this->section = Section::factory()->create(['school_id' => $this->teacher->school_id,
        'school_year_id' => SchoolYear::factory()->create(['school_id' => $this->teacher->school_id])->id,
        'adviser_id' => $this->teacher->id]);
    $this->data = ['first_name' => 'Fictional', 'last_name' => 'Example', 'birthdate' => '2015-05-03',
        'lrn_status' => 'pending', 'section_id' => $this->section->id, 'enrolled_on' => '2026-10-11',
        'is_transferee' => false, 'consent_confirmed' => true];
    $this->actingAs($this->teacher);
});

test('saving learner creates protected identity consent enrollment and a minimal audit entry atomically', function () {
    $this->post('/learners', [...$this->data, 'lrn' => '001234567890', 'lrn_status' => 'issued', 'health_notes' => 'Fictional health note'])->assertRedirect('/learners')->assertSessionHas('learner_created', true);
    $row = DB::table('learners')->first();
    expect($row->smile_id)->toMatch('/^SM-[A-HJ-NP-Z2-9]{8}$/')->not->toBe($row->lrn);
    expect($row->lrn)->toBe('001234567890');
    expect($row->consent_confirmed_by)->toBe($this->teacher->id);
    expect($row->consent_confirmed_at)->not->toBeNull();
    expect($row->health_notes)->not->toBe('Fictional health note');
    expect(Learner::query()->first()->health_notes)->toBe('Fictional health note');
    expect(Learner::query()->first()->toArray())->not->toHaveKey('health_notes');
    $this->assertDatabaseHas('smile_id_reservations', ['smile_id' => $row->smile_id]);
    $this->assertDatabaseHas('enrollments', ['learner_id' => $row->id, 'school_id' => $this->teacher->school_id,
        'school_year_id' => $this->section->school_year_id, 'section_id' => $this->section->id,
        'level_status' => 'non_graded', 'grade_level' => null, 'lis_enrollment_status' => 'pending']);
    $audit = DB::table('activity_log')->first();
    expect($audit->school_id)->toBe($this->teacher->school_id);
    expect($audit->event)->toBe('created');
    expect($audit->causer_id)->toBe($this->teacher->id);
    expect($audit->subject_id)->toBe($row->id);
    expect(json_encode($audit))->not->toContain('Fictional', '001234567890', 'health note');
});

test('optional LRN and missing attachments do not block enrollment', function () {
    $this->post('/learners', [...$this->data, 'lrn' => ''])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learners', ['lrn' => null, 'lrn_status' => 'pending']);
    $this->assertDatabaseCount('enrollments', 1);
});

test('invalid LRN formats and issued without LRN are rejected', function ($lrn, string $status) {
    $this->post('/learners', [...$this->data, 'lrn' => $lrn, 'lrn_status' => $status])->assertSessionHasErrors('lrn');
    $this->assertDatabaseCount('learners', 0);
    $this->assertDatabaseCount('smile_id_reservations', 0);
})->with([['123', 'pending'], ['00123456789A', 'issued'], [123456789012, 'issued'], [null, 'issued']]);

test('consent must be confirmed before collecting learner data', function ($consent) {
    $this->post('/learners', [...$this->data, 'consent_confirmed' => $consent])->assertSessionHasErrors('consent_confirmed');
    foreach (['learners', 'enrollments', 'smile_id_reservations', 'activity_log'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
})->with([false, null]);

test('server owned identity enrollment and consent attributes cannot be supplied', function (string $field, $value) {
    $this->post('/learners', [...$this->data, $field => $value])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('learners', 0);
})->with([['school_id', 1], ['smile_id', 'SM-ABCDEFGH'], ['school_year_id', 1], ['level_status', 'graded'], ['grade_level', 'Grade 1'], ['consent_confirmed_by', 'fake'], ['consent_confirmed_at', '2026-01-01']]);

test('dual role teacher cannot enroll into another advisers or another schools section', function (bool $otherSchool) {
    $schoolId = $otherSchool ? School::factory()->create()->id : $this->teacher->school_id;
    $adviser = User::factory()->create(['school_id' => $schoolId]);
    $adviser->assignRole('sped_teacher');
    $section = Section::factory()->create(['school_id' => $schoolId,
        'school_year_id' => SchoolYear::factory()->create(['school_id' => $schoolId])->id, 'adviser_id' => $adviser->id]);
    $this->post('/learners', [...$this->data, 'section_id' => $section->id])->assertSessionHasErrors('section_id');
    $this->get('/learners/create')->assertInertia(fn (Assert $page) => $page->has('sections', 1)->where('sections.0.id', $this->section->id));
    $this->assertDatabaseCount('learners', 0);
})->with([false, true]);

test('non teachers and system admins have no learner creation or roster access', function (array $roles) {
    $user = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $user->assignRole($roles);
    $this->actingAs($user)->get('/learners/create')->assertForbidden();
    $this->post('/learners', $this->data)->assertForbidden();
    expect(Learner::query()->count())->toBe(0);
})->with([[['parent']], [['regular_teacher']], [['school_admin']], [['system_admin', 'sped_teacher', 'school_admin']]]);

test('same school duplicate warning does not block saving or reveal matched learner records', function () {
    $this->post('/learners', $this->data)->assertSessionHas('duplicate_warning', false);
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole(['sped_teacher', 'school_admin']);
    $section = Section::factory()->create(['school_id' => $other->school_id, 'school_year_id' => $this->section->school_year_id, 'adviser_id' => $other->id]);
    $this->actingAs($other)->post('/learners', [...$this->data, 'first_name' => 'fictional', 'section_id' => $section->id])->assertSessionHas('duplicate_warning', true)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('learners', 2);
    $this->get('/learners')->assertInertia(fn (Assert $page) => $page->has('learners.data', 1)->where('learners.data.0.name', 'fictional Example')->where('duplicateWarning', true));
});

test('cross school matching names do not produce duplicate signals but LRN uniqueness is global', function () {
    $this->post('/learners', [...$this->data, 'lrn' => '001234567890'])->assertSessionHasNoErrors();
    $other = User::factory()->create();
    $other->assignRole(['sped_teacher', 'school_admin']);
    $section = Section::factory()->create(['school_id' => $other->school_id, 'school_year_id' => SchoolYear::factory()->create(['school_id' => $other->school_id])->id, 'adviser_id' => $other->id]);
    $this->actingAs($other)->post('/learners', [...$this->data, 'section_id' => $section->id])->assertSessionHas('duplicate_warning', false);
    $this->post('/learners', [...$this->data, 'section_id' => $section->id, 'lrn' => '001234567890'])->assertSessionHasErrors(['lrn' => __('learners.lrn_unavailable')]);
});

test('roster and model policies isolate advised learners even for school admins', function () {
    $this->post('/learners', $this->data)->assertSessionHasNoErrors();
    $learner = Learner::query()->firstOrFail();
    $this->get('/learners')->assertInertia(fn (Assert $page) => $page->has('learners.data', 1)->where('learners.data.0.smile_id', $learner->smile_id)->missing('learners.data.0.health_notes')->missing('learners.data.0.birthdate'));
    $this->assertDatabaseHas('activity_log', ['subject_id' => $learner->id, 'event' => 'viewed']);
    foreach ([User::factory()->create(['school_id' => $this->teacher->school_id]), User::factory()->create()] as $other) {
        $other->assignRole(['sped_teacher', 'school_admin']);
        $this->actingAs($other)->get('/learners')->assertInertia(fn (Assert $page) => $page->has('learners.data', 0));
        expect(Learner::query()->count())->toBe(0);
        expect(Enrollment::query()->count())->toBe(0);
        expect(Gate::forUser($other)->allows('view', $learner))->toBeFalse();
        expect(Gate::forUser($other)->allows('update', $learner))->toBeFalse();
    }
});

test('audit failure rolls back learner enrollment and identifier reservation without logging sensitive exception content', function () {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Sensitive fictional name and health content'));
    $this->post('/learners', $this->data)->assertSessionHasErrors(['form' => __('learners.save_failed')]);
    foreach (['learners', 'enrollments', 'smile_id_reservations', 'activity_log'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    Log::shouldHaveReceived('error')->once()->with('Learner creation failed', ['exception_type' => RuntimeException::class]);
});

test('disabled audit logger blocks saving and roster delivery', function () {
    $this->post('/learners', $this->data)->assertSessionHasNoErrors();
    app(ActivityLogStatus::class)->disable();
    $this->post('/learners', [...$this->data, 'first_name' => 'Another'])->assertSessionHasErrors('form');
    $this->assertDatabaseCount('learners', 1);
    $this->get('/learners')->assertStatus(503)->assertDontSee('Fictional');
});

test('learner identifiers retry collisions and cannot be changed through models or SQL', function () {
    DB::table('smile_id_reservations')->insert(['smile_id' => 'SM-ABCDEFGH']);
    $this->mock(SmileIdGenerator::class)->shouldReceive('generate')->twice()->andReturn('SM-ABCDEFGH', 'SM-23456789');
    $this->post('/learners', $this->data)->assertSessionHasNoErrors();
    $learner = Learner::query()->firstOrFail();
    expect($learner->smile_id)->toBe('SM-23456789');
    $learner->smile_id = 'SM-ABCDEFGH';
    expect(fn () => $learner->save())->toThrow(LogicException::class);
    expect(fn () => DB::transaction(fn () => DB::table('learners')->where('id', $learner->id)->update(['smile_id' => 'SM-ABCDEFGH'])))->toThrow(QueryException::class);
});

test('non graded learner cannot become graded without the future approved level change workflow', function () {
    $this->post('/learners', $this->data)->assertSessionHasNoErrors();
    expect(fn () => DB::transaction(fn () => DB::table('enrollments')->update(['level_status' => 'graded', 'grade_level' => 'Grade 1'])))->toThrow(QueryException::class);
    $this->assertDatabaseHas('enrollments', ['level_status' => 'non_graded', 'grade_level' => null]);
});

test('database rejects enrollment with another school or a section from another year', function () {
    $this->post('/learners', $this->data)->assertSessionHasNoErrors();
    $otherSchool = School::factory()->create();
    expect(fn () => DB::transaction(fn () => DB::table('enrollments')->update(['school_id' => $otherSchool->id])))->toThrow(QueryException::class);
    $year = SchoolYear::factory()->create(['school_id' => $this->teacher->school_id]);
    expect(fn () => DB::transaction(fn () => DB::table('enrollments')->update(['school_year_id' => $year->id])))->toThrow(QueryException::class);
});

test('repeated annual enrollment is rejected and failed validation does not flash learner attributes', function () {
    $this->post('/learners', $this->data)->assertSessionHasNoErrors();
    $enrollment = (array) DB::table('enrollments')->first();
    unset($enrollment['id']);
    expect(fn () => DB::transaction(fn () => DB::table('enrollments')->insert($enrollment)))->toThrow(QueryException::class);
    $this->post('/learners', [...$this->data, 'consent_confirmed' => false, 'lrn' => '001234567890', 'health_notes' => 'Fictional sensitive note'])->assertSessionHasErrors('consent_confirmed')->assertSessionMissing('_old_input.first_name')->assertSessionMissing('_old_input.lrn')->assertSessionMissing('_old_input.health_notes');
});
