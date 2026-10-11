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
        'lrn_status' => 'pending', 'health_notes' => 'Fictional initial health note',
        'section_id' => $this->section->id, 'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $this->learner = Learner::query()->firstOrFail();
    $this->url = '/learners/'.$this->learner->id;
    $this->data = ['first_name' => 'Fictional Updated', 'last_name' => 'Example', 'birthdate' => '2015-05-03',
        'middle_name' => 'Test', 'sex' => 'F', 'address' => 'Fictional address', 'mother_tongue' => 'Test language', 'health_notes' => 'Fictional changed health note'];
});

test('own adviser records an issued text LRN once without changing identity consent or enrollment', function () {
    $original = (array) DB::table('learners')->first();
    $enrollment = (array) DB::table('enrollments')->first();
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('canRecordLrn', true)->where('learner.lrn_status', 'pending'));
    $this->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertRedirect($this->url)->assertSessionHas('lrn_recorded', true);
    $updated = (array) DB::table('learners')->first();
    expect($updated['lrn'])->toBe('001234567890');
    expect($updated['lrn_status'])->toBe('issued');
    unset($updated['lrn'], $updated['lrn_status'], $original['lrn'], $original['lrn_status']);
    expect($updated)->toBe($original);
    expect((array) DB::table('enrollments')->first())->toBe($enrollment);
    $this->assertDatabaseCount('smile_id_reservations', 1);
    $this->assertDatabaseHas('activity_log', ['event' => 'lrn_recorded', 'subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id]);
    expect(json_encode(DB::table('activity_log')->get()))->not->toContain('001234567890', 'Fictional');
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('canRecordLrn', false)->where('learner.lrn', '001234567890')->where('learner.lrn_status', 'issued'));
    $this->post($this->url.'/lrn', ['lrn' => '001234567891'])->assertForbidden();
    $this->post($this->url.'/lrn', ['lrn' => null])->assertForbidden();
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => '001234567890']);
});

test('recording requires exactly twelve ASCII digits as a string without flashing the LRN', function ($lrn) {
    $this->post($this->url.'/lrn', ['lrn' => $lrn])->assertSessionHasErrors('lrn')->assertSessionMissing('_old_input.lrn');
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => null, 'lrn_status' => 'pending']);
    $this->assertDatabaseCount('activity_log', 1);
})->with([null, '', '12345678901', '1234567890123', '12345678901A', '12345678901-', [123456789012]]);

test('recording prohibits status ownership identity enrollment and consent injection', function (string $field) {
    $this->post($this->url.'/lrn', ['lrn' => '001234567890', $field => 'untrusted'])->assertSessionHasErrors($field);
    $this->assertDatabaseHas('learners', ['lrn' => null, 'lrn_status' => 'pending']);
})->with(['lrn_status', 'smile_id', 'school_id', 'first_name', 'health_notes', 'section_id', 'grade_level', 'lis_enrollment_status', 'consent_confirmed_by']);

test('empty protected fields cannot modify unrelated learner fields', function () {
    $this->post($this->url.'/lrn', ['lrn' => '001234567890', 'lrn_status' => null, 'smile_id' => null, 'school_id' => null, 'health_notes' => null])->assertSessionHasNoErrors();
    expect($this->learner->fresh()->health_notes)->toBe('Fictional initial health note');
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'school_id' => $this->teacher->school_id, 'smile_id' => $this->learner->smile_id, 'lrn_status' => 'issued']);
});

test('LRN conflicts return only a generic availability message even for another school or adviser', function (bool $sameSchool) {
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole('sped_teacher');
    $section = Section::factory()->create(['school_id' => $other->school_id, 'school_year_id' => SchoolYear::factory()->create(['school_id' => $other->school_id])->id, 'adviser_id' => $other->id]);
    $this->actingAs($other)->post('/learners', ['first_name' => 'Fictional Private', 'last_name' => 'Hidden', 'birthdate' => '2016-01-01',
        'lrn' => '001234567890', 'lrn_status' => 'issued', 'section_id' => $section->id, 'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->teacher)->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertSessionHasErrors(['lrn' => __('learners.lrn_unavailable')])->assertSessionMissing('_old_input.lrn');
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => null, 'lrn_status' => 'pending']);
    expect(DB::table('activity_log')->where('event', 'lrn_recorded')->count())->toBe(0);
})->with([true, false]);

test('issued or correction flagged records cannot use the pending LRN workflow', function (bool $hasLrn) {
    DB::table('learners')->where('id', $this->learner->id)->update(['lrn' => $hasLrn ? '001234567890' : null, 'lrn_status' => $hasLrn ? 'pending' : 'needs_correction']);
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('canRecordLrn', false));
    $this->post($this->url.'/lrn', ['lrn' => '001234567891'])->assertForbidden();
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => $hasLrn ? '001234567890' : null]);
})->with([true, false]);

test('other advisers schools and unsupported roles cannot record LRNs', function (string $roles, bool $sameSchool) {
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(explode(',', $roles));
    $this->actingAs($other)->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertNotFound();
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => null]);
})->with([['sped_teacher,school_admin', true], ['sped_teacher,school_admin', false], ['parent', true], ['regular_teacher', true], ['school_admin', true], ['system_admin,sped_teacher,school_admin', true]]);

test('reassignment transfers LRN recording permission', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertNotFound();
    $this->actingAs($other)->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertRedirect($this->url)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('activity_log', ['event' => 'lrn_recorded', 'causer_id' => $other->id]);
});

test('inactive accounts and guests cannot record an LRN', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'is_active' => false]);
    $other->assignRole('sped_teacher');
    $this->actingAs($other)->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertNotFound();
    auth()->logout();
    $this->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertRedirect('/login');
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => null]);
});

test('audit failure rolls back the LRN and status and logs no identifying input', function () {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional learner LRN 001234567890'));
    $this->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertSessionHasErrors('form');
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => null, 'lrn_status' => 'pending']);
    Log::shouldHaveReceived('error')->once()->with('Learner LRN recording failed', ['exception_type' => RuntimeException::class]);
    $this->assertDatabaseCount('activity_log', 1);
});

test('disabled auditing prevents recording an LRN', function () {
    app(ActivityLogStatus::class)->disable();
    $this->post($this->url.'/lrn', ['lrn' => '001234567890'])->assertSessionHasErrors('form');
    $this->assertDatabaseHas('learners', ['id' => $this->learner->id, 'lrn' => null, 'lrn_status' => 'pending']);
});
