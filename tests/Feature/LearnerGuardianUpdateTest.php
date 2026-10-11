<?php

use App\Actions\AuditLearner;
use App\Models\Guardian;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
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
        'school_year_id' => SchoolYear::factory()->create(['school_id' => $this->teacher->school_id])->id,
        'adviser_id' => $this->teacher->id]);
    $this->actingAs($this->teacher)->post('/learners', ['first_name' => 'Fictional', 'last_name' => 'Learner',
        'birthdate' => '2015-05-03', 'lrn_status' => 'pending', 'section_id' => $this->section->id,
        'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $this->learner = Learner::query()->firstOrFail();
    $this->url = '/learners/'.$this->learner->id.'/guardians';
    $this->data = ['full_name' => 'Fictional Contact', 'relationship' => 'Guardian', 'phone' => '+63 000 000 0000',
        'email' => 'fictional@example.test', 'address' => 'Fictional address', 'is_primary' => true, 'is_emergency' => true];
});

beforeEach(function () {
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $this->guardian = Guardian::query()->firstOrFail();
    $this->updateUrl = $this->url.'/'.$this->guardian->id;
    $this->changes = [...$this->data, 'full_name' => 'Fictional Corrected', 'relationship' => 'Aunt',
        'phone' => null, 'email' => null, 'address' => null, 'is_primary' => false, 'is_emergency' => false];
});

test('contact correction updates only the selected guardian and link with an attribute free audit', function () {
    $this->post($this->url, [...$this->data, 'full_name' => 'Fictional Untouched'])->assertSessionHasNoErrors();
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('contacts.0.can_edit', true));
    $this->patch($this->updateUrl, $this->changes)->assertRedirect($this->url)->assertSessionHas('guardian_saved', true);
    $this->assertDatabaseHas('guardians', ['id' => $this->guardian->id, 'school_id' => $this->teacher->school_id,
        'full_name' => 'Fictional Corrected', 'relationship' => 'Aunt', 'phone' => null, 'email' => null, 'address' => null, 'user_id' => null]);
    $this->assertDatabaseHas('learner_guardians', ['guardian_id' => $this->guardian->id, 'learner_id' => $this->learner->id, 'is_primary' => false, 'is_emergency' => false]);
    $this->assertDatabaseHas('guardians', ['full_name' => 'Fictional Untouched', 'phone' => $this->data['phone']]);
    $this->assertDatabaseCount('guardians', 2);
    $this->assertDatabaseCount('users', 1);
    $audit = DB::table('activity_log')->where('event', 'guardian_updated')->first();
    expect($audit->subject_id)->toBe($this->learner->id);
    expect($audit->causer_id)->toBe($this->teacher->id);
    expect(json_encode($audit))->not->toContain('Fictional', 'Aunt', '+63', 'example.test');
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('contacts.0.full_name', 'Fictional Corrected')->where('contacts.0.is_primary', false));
});

test('guardian correction rejects protected ownership and account fields', function (string $field) {
    $this->patch($this->updateUrl, [...$this->changes, $field => 'untrusted'])->assertSessionHasErrors($field);
    expect($this->guardian->fresh()->full_name)->toBe($this->data['full_name']);
    expect(DB::table('activity_log')->where('event', 'guardian_updated')->count())->toBe(0);
})->with(['id', 'school_id', 'user_id', 'learner_id', 'guardian_id']);

test('empty protected fields cannot clear portal linkage or ownership', function () {
    DB::table('guardians')->where('id', $this->guardian->id)->update(['user_id' => $this->teacher->id]);
    $this->patch($this->updateUrl, [...$this->changes, 'user_id' => null, 'id' => '', 'school_id' => null, 'learner_id' => null, 'guardian_id' => null])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('guardians', ['id' => $this->guardian->id, 'user_id' => $this->teacher->id, 'school_id' => $this->teacher->school_id]);
    $this->assertDatabaseHas('learner_guardians', ['guardian_id' => $this->guardian->id, 'learner_id' => $this->learner->id]);
});

test('contact correction validates input without flashing contact details', function (string $field, $value) {
    $this->patch($this->updateUrl, [...$this->changes, $field => $value])->assertSessionHasErrors($field)
        ->assertSessionMissing('_old_input.full_name')->assertSessionMissing('_old_input.relationship')
        ->assertSessionMissing('_old_input.phone')->assertSessionMissing('_old_input.email')->assertSessionMissing('_old_input.address');
    expect($this->guardian->fresh()->full_name)->toBe($this->data['full_name']);
})->with([['full_name', ''], ['relationship', ''], ['email', 'invalid'], ['phone', str_repeat('1', 51)], ['is_primary', null], ['is_emergency', 'invalid']]);

test('contact corrections deny another adviser school and non teacher roles', function (string $role, bool $sameSchool) {
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(explode(',', $role));
    $this->actingAs($other)->patch($this->updateUrl, $this->changes)->assertNotFound();
    $this->assertDatabaseHas('guardians', ['id' => $this->guardian->id, 'full_name' => $this->data['full_name']]);
    expect(Gate::forUser($other)->allows('update', $this->guardian))->toBeFalse();
})->with([['sped_teacher,school_admin', true], ['sped_teacher,school_admin', false], ['parent', true], ['regular_teacher', true], ['system_admin,sped_teacher,school_admin', true]]);

test('nested correction cannot edit a guardian belonging to a different accessible learner', function () {
    $this->post('/learners', ['first_name' => 'Fictional Second', 'last_name' => 'Learner', 'birthdate' => '2016-01-01',
        'lrn_status' => 'pending', 'section_id' => $this->section->id, 'enrolled_on' => '2026-10-11',
        'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $second = Learner::query()->where('id', '!=', $this->learner->id)->firstOrFail();
    $this->patch('/learners/'.$second->id.'/guardians/'.$this->guardian->id, $this->changes)->assertForbidden();
    expect($this->guardian->fresh()->full_name)->toBe($this->data['full_name']);
});

test('shared contacts cannot be mutated even when the other learner is hidden by adviser scope', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    $section = Section::factory()->create(['school_id' => $other->school_id, 'school_year_id' => $this->section->school_year_id, 'adviser_id' => $other->id]);
    $this->actingAs($other)->post('/learners', ['first_name' => 'Fictional Hidden', 'last_name' => 'Learner', 'birthdate' => '2016-01-01',
        'lrn_status' => 'pending', 'section_id' => $section->id, 'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $hidden = Learner::query()->firstOrFail();
    DB::table('learner_guardians')->insert(['school_id' => $other->school_id, 'learner_id' => $hidden->id,
        'guardian_id' => $this->guardian->id, 'is_primary' => false, 'is_emergency' => false]);
    $this->actingAs($this->teacher)->get($this->url)->assertInertia(fn (Assert $page) => $page->where('contacts.0.can_edit', false)->has('contacts', 1));
    $this->patch($this->updateUrl, $this->changes)->assertForbidden();
    $this->assertDatabaseHas('guardians', ['id' => $this->guardian->id, 'full_name' => $this->data['full_name']]);
    $this->assertDatabaseHas('learner_guardians', ['learner_id' => $hidden->id, 'is_emergency' => false]);
});

test('reassignment guests and inactive teachers cannot correct contacts', function () {
    auth()->logout();
    $this->patch($this->updateUrl, $this->changes)->assertRedirect('/login');
    DB::table('users')->where('id', $this->teacher->id)->update(['is_active' => false]);
    $this->teacher->is_active = false;
    $this->actingAs($this->teacher)->patch($this->updateUrl, $this->changes)->assertNotFound();
    DB::table('users')->where('id', $this->teacher->id)->update(['is_active' => true]);
    $this->teacher->is_active = true;
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->actingAs($this->teacher)->patch($this->updateUrl, $this->changes)->assertNotFound();
    $this->actingAs($other)->patch($this->updateUrl, $this->changes)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('activity_log', ['event' => 'guardian_updated', 'causer_id' => $other->id]);
});

test('audit failure rolls back contact fields and learner flags without logging private input', function () {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional private contact'));
    $this->patch($this->updateUrl, $this->changes)->assertSessionHasErrors('form');
    $this->assertDatabaseHas('guardians', ['id' => $this->guardian->id, 'full_name' => $this->data['full_name'], 'phone' => $this->data['phone']]);
    $this->assertDatabaseHas('learner_guardians', ['guardian_id' => $this->guardian->id, 'is_primary' => true, 'is_emergency' => true]);
    Log::shouldHaveReceived('error')->once()->with('Guardian update failed', ['exception_type' => RuntimeException::class]);
});

test('disabled audit prevents contact corrections', function () {
    app(ActivityLogStatus::class)->disable();
    $this->patch($this->updateUrl, $this->changes)->assertSessionHasErrors('form');
    $this->assertDatabaseHas('guardians', ['id' => $this->guardian->id, 'full_name' => $this->data['full_name']]);
    $this->assertDatabaseHas('learner_guardians', ['guardian_id' => $this->guardian->id, 'is_primary' => true]);
});
