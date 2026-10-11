<?php

use App\Actions\AuditLearner;
use App\Models\Learner;
use App\Models\LearnerDisability;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\TeacherAssessment;
use App\Models\User;
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
        'school_year_id' => SchoolYear::factory()->create(['school_id' => $this->teacher->school_id])->id, 'adviser_id' => $this->teacher->id]);
    $this->actingAs($this->teacher)->post('/learners', ['first_name' => 'Fictional', 'last_name' => 'Example', 'birthdate' => '2015-05-03',
        'lrn_status' => 'pending', 'section_id' => $this->section->id, 'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $this->learner = Learner::query()->firstOrFail();
    $this->url = '/learners/'.$this->learner->id.'/assessments';
    $this->teacherData = ['kind' => 'teacher', 'assessed_on' => '2026-10-11', 'observations' => 'Fictional classroom test and observed finding'];
    $this->disabilityData = ['kind' => 'disability', 'category_id' => DB::table('disability_categories')->value('id'),
        'diagnosis_date' => '2026-09-01', 'assessed_by' => 'Fictional clinic', 'notes' => 'Fictional supporting clinical note'];
});

test('teacher assessment is separate from diagnoses encrypted and attributed to the actor', function () {
    $this->post($this->url, $this->teacherData)->assertRedirect($this->url)->assertSessionHas('assessment_saved', true);
    $record = TeacherAssessment::query()->firstOrFail();
    expect($record->observations)->toBe($this->teacherData['observations']);
    expect($record->recorded_by)->toBe($this->teacher->id);
    expect($record->toArray())->not->toHaveKey('observations');
    expect(DB::table('teacher_assessments')->value('observations'))->not->toContain('Fictional');
    $this->assertDatabaseCount('learner_disabilities', 0);
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->component('learners/assessments')->has('records.teacher', 1)->has('records.disabilities', 0)->has('categories', 10)->where('records.teacher.0.observations', $this->teacherData['observations'])->where('learner.smile_id', $this->learner->smile_id)->missing('learner.health_notes'));
    $this->assertDatabaseHas('activity_log', ['event' => 'teacher_assessment_created', 'subject_id' => $this->learner->id]);
    $this->assertDatabaseHas('activity_log', ['event' => 'assessments_viewed', 'subject_id' => $this->learner->id]);
    expect(json_encode(DB::table('activity_log')->get()))->not->toContain('Fictional', 'classroom test');
    $this->assertDatabaseHas('enrollments', ['level_status' => 'non_graded', 'grade_level' => null]);
});

test('clinical notes dates and provider names are encrypted and disclosed only in an audited view', function () {
    $this->post($this->url, $this->disabilityData)->assertSessionHasNoErrors();
    $record = LearnerDisability::query()->firstOrFail();
    foreach (['notes', 'assessed_by', 'diagnosis_date'] as $field) {
        expect($record->$field)->toBe($this->disabilityData[$field]);
        expect(DB::table('learner_disabilities')->value($field))->not->toBe($this->disabilityData[$field]);
        expect($record->toArray())->not->toHaveKey($field);
    }
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->has('records.disabilities', 1)->where('records.disabilities.0.notes', $this->disabilityData['notes'])->where('records.disabilities.0.diagnosis_date', '2026-09-01'));
    $this->assertDatabaseHas('activity_log', ['event' => 'disability_record_created', 'causer_id' => $this->teacher->id]);
    $this->assertDatabaseCount('teacher_assessments', 0);
});

test('missing medical documentation does not block teacher assessment or disability information', function () {
    $this->post($this->url, $this->teacherData)->assertSessionHasNoErrors();
    $this->post($this->url, ['kind' => 'disability', 'category_id' => $this->disabilityData['category_id']])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learner_disabilities', ['notes' => null, 'diagnosis_date' => null, 'assessed_by' => null]);
    $this->assertDatabaseCount('enrollments', 1);
});

test('assessment validation rejects invalid records and protects sensitive old input', function (array $change, string $field, bool $teacher) {
    $data = $teacher ? $this->teacherData : $this->disabilityData;
    $this->post($this->url, [...$data, ...$change])->assertSessionHasErrors($field)
        ->assertSessionMissing('_old_input.observations')->assertSessionMissing('_old_input.notes')->assertSessionMissing('_old_input.diagnosis_date')->assertSessionMissing('_old_input.assessed_by');
    $this->assertDatabaseCount('teacher_assessments', 0);
    $this->assertDatabaseCount('learner_disabilities', 0);
})->with([
    [['observations' => ''], 'observations', true], [['assessed_on' => '2099-01-01'], 'assessed_on', true],
    [['category_id' => 1], 'category_id', true], [['kind' => 'diagnose_automatically'], 'kind', true],
    [['category_id' => 99999], 'category_id', false], [['diagnosis_date' => 'invalid'], 'diagnosis_date', false],
    [['observations' => 'Fictional observation'], 'observations', false], [['notes' => str_repeat('a', 10001)], 'notes', false],
]);

test('client cannot supply ownership or recorder attributes', function (string $field) {
    $this->post($this->url, [...$this->teacherData, $field => 'untrusted'])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('teacher_assessments', 0);
})->with(['id', 'school_id', 'learner_id', 'recorded_by', 'created_at']);

test('other adviser or school cannot read write edit or query assessment records', function (bool $sameSchool) {
    $this->post($this->url, $this->teacherData)->assertSessionHasNoErrors();
    $this->post($this->url, $this->disabilityData)->assertSessionHasNoErrors();
    $assessment = TeacherAssessment::query()->firstOrFail();
    $disability = LearnerDisability::query()->firstOrFail();
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(['sped_teacher', 'school_admin']);
    $this->actingAs($other)->get($this->url)->assertNotFound();
    $this->post($this->url, $this->teacherData)->assertNotFound();
    expect(TeacherAssessment::query()->count())->toBe(0);
    expect(LearnerDisability::query()->count())->toBe(0);
    foreach ([$assessment, $disability] as $record) {
        expect(Gate::forUser($other)->allows('view', $record))->toBeFalse();
        expect(Gate::forUser($other)->allows('update', $record))->toBeFalse();
    }
})->with([true, false]);

test('unsupported roles cannot access assessments even with teacher roles for system admins', function (array $roles) {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole($roles);
    $this->actingAs($other)->get($this->url)->assertNotFound();
    $this->post($this->url, $this->teacherData)->assertNotFound();
})->with([[['parent']], [['regular_teacher']], [['school_admin']], [['system_admin', 'sped_teacher', 'school_admin']]]);

test('adviser reassignment removes assessment access and guests and inactive accounts cannot read', function () {
    $this->post($this->url, $this->teacherData)->assertSessionHasNoErrors();
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->get($this->url)->assertNotFound();
    $this->actingAs($other)->get($this->url)->assertOk();
    $other->is_active = false;
    $other->save();
    $this->get($this->url)->assertNotFound();
    auth()->logout();
    $this->get($this->url)->assertRedirect('/login');
});

test('audit failure rolls back either kind without logging clinical data', function (bool $teacher) {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional private medical note'));
    $this->post($this->url, $teacher ? $this->teacherData : $this->disabilityData)->assertSessionHasErrors('form');
    $this->assertDatabaseCount('teacher_assessments', 0);
    $this->assertDatabaseCount('learner_disabilities', 0);
    $this->assertDatabaseCount('activity_log', 1);
    Log::shouldHaveReceived('error')->once()->with('Assessment creation failed', ['exception_type' => RuntimeException::class]);
})->with([true, false]);

test('disabled auditing prevents disclosure and saving', function () {
    $this->post($this->url, $this->disabilityData)->assertSessionHasNoErrors();
    app(ActivityLogStatus::class)->disable();
    $this->get($this->url)->assertStatus(503)->assertDontSee('Fictional');
    $this->post($this->url, $this->teacherData)->assertSessionHasErrors('form');
    $this->assertDatabaseCount('teacher_assessments', 0);
});

test('database rejects cross school records recorders and unknown categories', function () {
    $this->post($this->url, $this->teacherData)->assertSessionHasNoErrors();
    $this->post($this->url, $this->disabilityData)->assertSessionHasNoErrors();
    $other = User::factory()->create();
    expect(fn () => DB::transaction(fn () => DB::table('teacher_assessments')->update(['school_id' => $other->school_id])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('teacher_assessments')->update(['recorded_by' => $other->id])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('learner_disabilities')->update(['school_id' => $other->school_id])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('learner_disabilities')->update(['category_id' => 99999])))->toThrow(QueryException::class);
});
