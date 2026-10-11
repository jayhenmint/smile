<?php

use App\Actions\AuditLearner;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
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
        'lrn' => '001234567890', 'lrn_status' => 'issued', 'health_notes' => 'Fictional initial health note',
        'section_id' => $this->section->id, 'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $this->learner = Learner::query()->firstOrFail();
    $this->url = '/learners/'.$this->learner->id;
    $this->data = ['first_name' => 'Fictional Updated', 'last_name' => 'Example', 'birthdate' => '2015-05-03',
        'middle_name' => 'Test', 'sex' => 'F', 'address' => 'Fictional address', 'mother_tongue' => 'Test language', 'health_notes' => 'Fictional changed health note'];
});

beforeEach(function () {
    $this->nextYear = SchoolYear::factory()->create(['school_id' => $this->teacher->school_id, 'label' => 'Test next school year']);
    $this->nextSection = Section::factory()->create(['school_id' => $this->teacher->school_id, 'school_year_id' => $this->nextYear->id, 'adviser_id' => $this->teacher->id]);
    $this->enrollUrl = $this->url.'/enrollments';
    $this->enrollData = ['section_id' => $this->nextSection->id, 'enrolled_on' => '2026-10-12'];
});

test('annual enrollment reuses the learner and preserves identity consent health and previous enrollment', function () {
    $original = (array) DB::table('learners')->first();
    $oldEnrollment = (array) DB::table('enrollments')->first();
    $this->get($this->enrollUrl.'/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('learners/enroll')->has('sections', 1)
        ->where('sections.0.id', $this->nextSection->id)->where('sections.0.year', $this->nextYear->label)->where('learner.smile_id', $this->learner->smile_id)
        ->missing('learner.health_notes')->missing('learner.birthdate')->missing('learner.lrn'));
    $this->assertDatabaseHas('activity_log', ['event' => 'enrollment_form_viewed', 'subject_id' => $this->learner->id]);
    $this->post($this->enrollUrl, $this->enrollData)->assertRedirect($this->url)->assertSessionHas('enrollment_saved', true);
    $this->assertDatabaseCount('learners', 1);
    $this->assertDatabaseCount('smile_id_reservations', 1);
    $this->assertDatabaseCount('enrollments', 2);
    expect((array) DB::table('learners')->first())->toBe($original);
    expect((array) DB::table('enrollments')->where('id', $oldEnrollment['id'])->first())->toBe($oldEnrollment);
    $this->assertDatabaseHas('enrollments', ['school_id' => $this->teacher->school_id, 'learner_id' => $this->learner->id, 'school_year_id' => $this->nextYear->id, 'section_id' => $this->nextSection->id,
        'level_status' => 'non_graded', 'grade_level' => null, 'lis_enrollment_status' => 'pending', 'is_transferee' => false, 'previous_school' => null]);
    $this->assertDatabaseHas('activity_log', ['event' => 'enrollment_created', 'subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id]);
    expect(json_encode(DB::table('activity_log')->get()))->not->toContain('Fictional', '001234567890');
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->has('learner.enrollments', 2));
    $this->get($this->enrollUrl.'/create')->assertInertia(fn (Assert $page) => $page->has('sections', 0));
    $this->post($this->enrollUrl, $this->enrollData)->assertSessionHasErrors(['section_id' => __('learners.already_enrolled')]);
    $this->assertDatabaseCount('enrollments', 2);
});

test('LRN and medical attachments are not prerequisites for annual non graded enrollment', function () {
    DB::table('learners')->where('id', $this->learner->id)->update(['lrn' => null, 'lrn_status' => 'pending']);
    $this->post($this->enrollUrl, $this->enrollData)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('enrollments', 2);
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => null, 'lrn_status' => 'pending']);
});

test('duplicate enrollment is prevented across sections including enrollment hidden by adviser scope', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    $hiddenSection = Section::factory()->create(['school_id' => $other->school_id, 'school_year_id' => $this->nextYear->id, 'adviser_id' => $other->id]);
    DB::table('enrollments')->insert(['school_id' => $other->school_id, 'learner_id' => $this->learner->id, 'school_year_id' => $this->nextYear->id, 'section_id' => $hiddenSection->id, 'enrolled_on' => '2026-10-11']);
    $this->get($this->enrollUrl.'/create')->assertInertia(fn (Assert $page) => $page->has('sections', 0));
    $this->post($this->enrollUrl, $this->enrollData)->assertSessionHasErrors(['section_id' => __('learners.already_enrolled')]);
    $this->assertDatabaseCount('enrollments', 2);
});

test('ownership level LIS status and transferee metadata cannot be supplied for returning enrollment', function (string $field) {
    $this->post($this->enrollUrl, [...$this->enrollData, $field => 'untrusted'])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('enrollments', 1);
})->with(['learner_id', 'school_id', 'school_year_id', 'smile_id', 'lrn', 'level_status', 'grade_level', 'lis_enrollment_status', 'is_transferee', 'previous_school', 'status']);

test('empty prohibited values cannot override default non graded enrollment or learner identity', function () {
    $this->post($this->enrollUrl, [...$this->enrollData, 'school_id' => null, 'school_year_id' => null, 'grade_level' => null, 'level_status' => null, 'lrn' => null])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('enrollments', ['school_year_id' => $this->nextYear->id, 'level_status' => 'non_graded', 'grade_level' => null]);
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => '001234567890']);
});

test('invalid enrollment inputs do not create a record', function (array $change, string $field) {
    $this->post($this->enrollUrl, [...$this->enrollData, ...$change])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('enrollments', 1);
})->with([[['section_id' => null], 'section_id'], [['section_id' => 99999], 'section_id'], [['enrolled_on' => 'invalid'], 'enrolled_on']]);

test('target sections must belong to this school and adviser', function (bool $sameSchool) {
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole('sped_teacher');
    $section = Section::factory()->create(['school_id' => $other->school_id, 'school_year_id' => SchoolYear::factory()->create(['school_id' => $other->school_id])->id, 'adviser_id' => $other->id]);
    $this->post($this->enrollUrl, [...$this->enrollData, 'section_id' => $section->id])->assertSessionHasErrors('section_id');
    $this->get($this->enrollUrl.'/create')->assertInertia(fn (Assert $page) => $page->has('sections', 1)->where('sections.0.id', $this->nextSection->id));
})->with([true, false]);

test('other advisers schools and unsupported roles cannot view or save annual enrollment', function (string $roles, bool $sameSchool) {
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(explode(',', $roles));
    $this->actingAs($other)->get($this->enrollUrl.'/create')->assertNotFound();
    $this->post($this->enrollUrl, $this->enrollData)->assertNotFound();
    $this->assertDatabaseCount('enrollments', 1);
})->with([['sped_teacher,school_admin', true], ['sped_teacher,school_admin', false], ['parent', true], ['regular_teacher', true], ['school_admin', true], ['system_admin,sped_teacher,school_admin', true]]);

test('adviser reassignment removes existing learner and target section access', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->nextSection->id)->update(['adviser_id' => $other->id]);
    $this->post($this->enrollUrl, $this->enrollData)->assertSessionHasErrors('section_id');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->get($this->enrollUrl.'/create')->assertNotFound();
    $this->post($this->enrollUrl, $this->enrollData)->assertNotFound();
    $this->actingAs($other)->post($this->enrollUrl, $this->enrollData)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('activity_log', ['event' => 'enrollment_created', 'causer_id' => $other->id]);
});

test('inactive guests and non enrolled learner statuses cannot enter the returning enrollment flow', function (string $status) {
    DB::table('learners')->where('id', $this->learner->id)->update(['status' => $status]);
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('canReenroll', false));
    $this->get($this->enrollUrl.'/create')->assertForbidden();
    $this->post($this->enrollUrl, $this->enrollData)->assertForbidden();
    $this->teacher->is_active = false;
    $this->get($this->enrollUrl.'/create')->assertNotFound();
    auth()->logout();
    $this->post($this->enrollUrl, $this->enrollData)->assertRedirect('/login');
})->with(['transferred_out', 'dropped', 'graduated']);

test('audit failure rolls back enrollment and keeps error logs private', function () {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional private learner 001234567890'));
    $this->post($this->enrollUrl, $this->enrollData)->assertSessionHasErrors('form');
    $this->assertDatabaseCount('enrollments', 1);
    $this->assertDatabaseCount('learners', 1);
    Log::shouldHaveReceived('error')->once()->with('Enrollment creation failed', ['exception_type' => RuntimeException::class]);
});

test('disabled audits block annual enrollment views and saving', function () {
    app(ActivityLogStatus::class)->disable();
    $this->get($this->enrollUrl.'/create')->assertStatus(503)->assertDontSee('Fictional');
    $this->post($this->enrollUrl, $this->enrollData)->assertSessionHasErrors('form');
    $this->assertDatabaseCount('enrollments', 1);
});
