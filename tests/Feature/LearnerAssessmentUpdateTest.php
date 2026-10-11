<?php

use App\Actions\AuditLearner;
use App\Models\Learner;
use App\Models\LearnerDisability;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\TeacherAssessment;
use App\Models\User;
use Database\Seeders\RolesSeeder;
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

beforeEach(function () {
    $this->post($this->url, $this->teacherData)->assertSessionHasNoErrors();
    $this->post($this->url, $this->disabilityData)->assertSessionHasNoErrors();
    $this->assessment = TeacherAssessment::query()->firstOrFail();
    $this->disability = LearnerDisability::query()->firstOrFail();
    $this->teacherUrl = $this->url.'/teacher/'.$this->assessment->id;
    $this->disabilityUrl = $this->url.'/disability/'.$this->disability->id;
    $this->teacherChanges = [...$this->teacherData, 'assessed_on' => '2026-10-10', 'observations' => 'Fictional corrected classroom finding'];
    $this->disabilityChanges = [...$this->disabilityData, 'category_id' => DB::table('disability_categories')->orderByDesc('id')->value('id'),
        'diagnosis_date' => '2026-08-01', 'assessed_by' => 'Fictional corrected clinic', 'notes' => 'Fictional corrected clinical note'];
});

test('assessment corrections preserve ownership encryption and original attribution with safe audits', function (bool $teacher) {
    $record = $teacher ? $this->assessment : $this->disability;
    $data = $teacher ? $this->teacherChanges : $this->disabilityChanges;
    $table = $record->getTable();
    $original = DB::table($table)->where('id', $record->id)->first();
    $this->patch($teacher ? $this->teacherUrl : $this->disabilityUrl, $data)->assertRedirect($this->url)->assertSessionHas('assessment_saved', true);
    $updated = $record->fresh();
    $raw = DB::table($table)->where('id', $record->id)->first();
    foreach ($teacher ? ['observations'] : ['diagnosis_date', 'assessed_by', 'notes'] as $field) {
        expect($updated->$field)->toBe($data[$field]);
        expect($raw->$field)->not->toBe($data[$field]);
        expect($updated->toArray())->not->toHaveKey($field);
    }
    expect($raw->school_id)->toBe($original->school_id);
    expect($raw->learner_id)->toBe($original->learner_id);
    if ($teacher) {
        expect($raw->recorded_by)->toBe($original->recorded_by);
        expect($raw->created_at)->toBe($original->created_at);
    } else {
        expect((int) $raw->category_id)->toBe($data['category_id']);
    }
    $this->assertDatabaseCount('teacher_assessments', 1);
    $this->assertDatabaseCount('learner_disabilities', 1);
    $this->assertDatabaseHas('enrollments', ['learner_id' => $this->learner->id, 'level_status' => 'non_graded', 'grade_level' => null]);
    $event = $teacher ? 'teacher_assessment_updated' : 'disability_record_updated';
    $this->assertDatabaseHas('activity_log', ['event' => $event, 'subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id]);
    expect(json_encode(DB::table('activity_log')->get()))->not->toContain('Fictional', 'clinic', 'classroom');
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where($teacher ? 'records.teacher.0.observations' : 'records.disabilities.0.notes', $teacher ? $data['observations'] : $data['notes'])
        ->where($teacher ? 'records.teacher.0.can_edit' : 'records.disabilities.0.can_edit', true)->missing('records.teacher.0.recorded_by')->missing('learner.health_notes'));
})->with([true, false]);

test('clinical details can be cleared while the disability category remains required', function () {
    $this->patch($this->disabilityUrl, [...$this->disabilityChanges, 'diagnosis_date' => null, 'assessed_by' => null, 'notes' => null])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learner_disabilities', ['id' => $this->disability->id, 'diagnosis_date' => null, 'assessed_by' => null, 'notes' => null]);
});

test('corrections reject client ownership recorder and creation metadata', function (string $field) {
    foreach ([[$this->teacherUrl, $this->teacherChanges], [$this->disabilityUrl, $this->disabilityChanges]] as [$url, $data]) {
        $this->patch($url, [...$data, $field => 'untrusted'])->assertSessionHasErrors($field);
    }
    expect($this->assessment->fresh()->observations)->toBe($this->teacherData['observations']);
    expect($this->disability->fresh()->notes)->toBe($this->disabilityData['notes']);
})->with(['id', 'school_id', 'learner_id', 'recorded_by', 'created_at']);

test('empty prohibited fields cannot clear original recorder creation metadata or ownership', function () {
    $original = DB::table('teacher_assessments')->where('id', $this->assessment->id)->first();
    $this->patch($this->teacherUrl, [...$this->teacherChanges, 'id' => null, 'school_id' => null, 'learner_id' => null, 'recorded_by' => null, 'created_at' => null])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('teacher_assessments', ['id' => $original->id, 'school_id' => $original->school_id, 'learner_id' => $original->learner_id, 'recorded_by' => $original->recorded_by, 'created_at' => $original->created_at]);
    $this->assertDatabaseCount('learner_disabilities', 1);
});

test('correction validation preserves records and excludes sensitive session input', function (bool $teacher, array $change, string $field) {
    $this->patch($teacher ? $this->teacherUrl : $this->disabilityUrl, [...($teacher ? $this->teacherChanges : $this->disabilityChanges), ...$change])
        ->assertSessionHasErrors($field)->assertSessionMissing('_old_input.observations')->assertSessionMissing('_old_input.diagnosis_date')
        ->assertSessionMissing('_old_input.assessed_by')->assertSessionMissing('_old_input.notes');
    expect($this->assessment->fresh()->observations)->toBe($this->teacherData['observations']);
    expect($this->disability->fresh()->notes)->toBe($this->disabilityData['notes']);
    expect(DB::table('activity_log')->whereIn('event', ['teacher_assessment_updated', 'disability_record_updated'])->count())->toBe(0);
})->with([
    [true, ['observations' => ''], 'observations'], [true, ['assessed_on' => '2099-01-01'], 'assessed_on'],
    [true, ['category_id' => 1], 'category_id'], [true, ['kind' => 'disability'], 'kind'],
    [false, ['kind' => 'teacher'], 'kind'], [false, ['category_id' => 99999], 'category_id'],
    [false, ['diagnosis_date' => 'invalid'], 'diagnosis_date'], [false, ['notes' => str_repeat('a', 10001)], 'notes'],
]);

test('school adviser and unsupported role isolation protects both correction endpoints', function (string $roles, bool $sameSchool) {
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(explode(',', $roles));
    $this->actingAs($other)->patch($this->teacherUrl, $this->teacherChanges)->assertNotFound();
    $this->patch($this->disabilityUrl, $this->disabilityChanges)->assertNotFound();
    foreach ([$this->assessment, $this->disability] as $record) {
        expect(Gate::forUser($other)->allows('update', $record))->toBeFalse();
    }
    $this->assertDatabaseCount('teacher_assessments', 1);
    $this->assertDatabaseCount('learner_disabilities', 1);
})->with([['sped_teacher,school_admin', true], ['sped_teacher,school_admin', false], ['parent', true], ['regular_teacher', true], ['school_admin', true], ['system_admin,sped_teacher,school_admin', true]]);

test('records from another accessible learner cannot be corrected through the wrong learner URL', function () {
    $this->post('/learners', ['first_name' => 'Fictional Second', 'last_name' => 'Example', 'birthdate' => '2016-01-01',
        'lrn_status' => 'pending', 'section_id' => $this->section->id, 'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $second = Learner::query()->where('id', '!=', $this->learner->id)->firstOrFail();
    $base = '/learners/'.$second->id.'/assessments';
    $this->patch($base.'/teacher/'.$this->assessment->id, $this->teacherChanges)->assertForbidden();
    $this->patch($base.'/disability/'.$this->disability->id, $this->disabilityChanges)->assertForbidden();
    expect($this->assessment->fresh()->observations)->toBe($this->teacherData['observations']);
    expect($this->disability->fresh()->notes)->toBe($this->disabilityData['notes']);
});

test('reassigned adviser can correct records without replacing the original recorder and inactive or guest users cannot', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->patch($this->teacherUrl, $this->teacherChanges)->assertNotFound();
    $this->patch($this->disabilityUrl, $this->disabilityChanges)->assertNotFound();
    $this->actingAs($other)->patch($this->teacherUrl, $this->teacherChanges)->assertSessionHasNoErrors();
    $this->patch($this->disabilityUrl, $this->disabilityChanges)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('teacher_assessments', ['id' => $this->assessment->id, 'recorded_by' => $this->teacher->id]);
    $this->assertDatabaseHas('activity_log', ['event' => 'teacher_assessment_updated', 'causer_id' => $other->id]);
    $other->is_active = false;
    $other->save();
    $this->patch($this->teacherUrl, $this->teacherChanges)->assertNotFound();
    $this->patch($this->disabilityUrl, $this->disabilityChanges)->assertNotFound();
    auth()->logout();
    $this->patch($this->teacherUrl, $this->teacherChanges)->assertRedirect('/login');
    $this->patch($this->disabilityUrl, $this->disabilityChanges)->assertRedirect('/login');
});

test('failed audit rolls back either correction and logs no clinical details', function (bool $teacher) {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional private medical finding'));
    $this->patch($teacher ? $this->teacherUrl : $this->disabilityUrl, $teacher ? $this->teacherChanges : $this->disabilityChanges)->assertSessionHasErrors('form');
    expect($this->assessment->fresh()->observations)->toBe($this->teacherData['observations']);
    expect($this->disability->fresh()->notes)->toBe($this->disabilityData['notes']);
    Log::shouldHaveReceived('error')->once()->with('Assessment update failed', ['exception_type' => RuntimeException::class]);
    expect(DB::table('activity_log')->whereIn('event', ['teacher_assessment_updated', 'disability_record_updated'])->count())->toBe(0);
})->with([true, false]);

test('disabled audit prevents both assessment corrections', function () {
    app(ActivityLogStatus::class)->disable();
    $this->patch($this->teacherUrl, $this->teacherChanges)->assertSessionHasErrors('form');
    $this->patch($this->disabilityUrl, $this->disabilityChanges)->assertSessionHasErrors('form');
    expect($this->assessment->fresh()->observations)->toBe($this->teacherData['observations']);
    expect($this->disability->fresh()->notes)->toBe($this->disabilityData['notes']);
});
