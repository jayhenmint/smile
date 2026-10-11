<?php

use App\Actions\AuditLearner;
use App\Models\Guardian;
use App\Models\Learner;
use App\Models\LearnerGuardian;
use App\Models\SchoolYear;
use App\Models\Section;
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

test('own adviser can save and view guardian contact information with learner audits', function () {
    $this->post($this->url, $this->data)->assertRedirect($this->url)->assertSessionHas('guardian_saved', true);
    $guardian = Guardian::query()->firstOrFail();
    expect($guardian->school_id)->toBe($this->teacher->school_id);
    expect($guardian->user_id)->toBeNull();
    $this->assertDatabaseHas('learner_guardians', ['school_id' => $this->teacher->school_id, 'learner_id' => $this->learner->id, 'guardian_id' => $guardian->id, 'is_primary' => true, 'is_emergency' => true]);
    $this->assertDatabaseHas('activity_log', ['subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id, 'event' => 'guardian_created']);
    $this->get($this->url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('learners/guardians')->where('learner.smile_id', $this->learner->smile_id)->has('contacts', 1)->where('contacts.0.full_name', 'Fictional Contact')->where('contacts.0.is_emergency', true)->missing('contacts.0.user_id')->missing('learner.health_notes')->missing('learner.birthdate'));
    $this->assertDatabaseHas('activity_log', ['subject_id' => $this->learner->id, 'event' => 'guardians_viewed']);
    $audit = DB::table('activity_log')->where('event', 'guardian_created')->first();
    expect(json_encode($audit))->not->toContain('Fictional', 'example.test', '+63');
    $this->assertDatabaseCount('users', 1);
});

test('multiple contacts and missing optional details are supported without blocking enrollment', function () {
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->has('contacts', 0));
    $this->post($this->url, ['full_name' => 'Fictional First', 'relationship' => 'Mother', 'is_primary' => false, 'is_emergency' => false])->assertSessionHasNoErrors();
    $this->post($this->url, [...$this->data, 'full_name' => 'Fictional Second'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('guardians', 2);
    $this->assertDatabaseCount('learner_guardians', 2);
    $this->assertDatabaseHas('guardians', ['full_name' => 'Fictional First', 'phone' => null, 'email' => null, 'address' => null]);
});

test('guardian validation rejects invalid or missing fields without saving or flashing contact details', function (string $field, $value) {
    $this->post($this->url, [...$this->data, $field => $value])->assertSessionHasErrors($field)->assertSessionMissing('_old_input.full_name')->assertSessionMissing('_old_input.phone')->assertSessionMissing('_old_input.email')->assertSessionMissing('_old_input.address');
    $this->assertDatabaseCount('guardians', 0);
    $this->assertDatabaseCount('learner_guardians', 0);
    $this->assertDatabaseCount('activity_log', 1);
})->with([['full_name', ''], ['relationship', ''], ['email', 'invalid'], ['phone', str_repeat('1', 51)], ['is_emergency', 'invalid'], ['is_primary', null]]);

test('guardian and link ownership and portal account cannot be supplied by the client', function (string $field) {
    $this->post($this->url, [...$this->data, $field => 'untrusted'])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('guardians', 0);
})->with(['id', 'guardian_id', 'learner_id', 'school_id', 'user_id']);

test('other adviser and other school cannot read create edit or query guardian contacts', function (bool $sameSchool) {
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $guardian = Guardian::query()->firstOrFail();
    $link = LearnerGuardian::query()->firstOrFail();
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(['sped_teacher', 'school_admin']);
    $this->actingAs($other)->get($this->url)->assertNotFound();
    $this->post($this->url, $this->data)->assertNotFound();
    expect(Guardian::query()->count())->toBe(0);
    expect(LearnerGuardian::query()->count())->toBe(0);
    expect(Gate::forUser($other)->allows('view', $guardian))->toBeFalse();
    expect(Gate::forUser($other)->allows('update', $guardian))->toBeFalse();
    expect(Gate::forUser($other)->allows('view', $link))->toBeFalse();
    $this->assertDatabaseCount('guardians', 1);
})->with([true, false]);

test('parent regular teacher and system admin do not receive guardian information', function (array $roles) {
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole($roles);
    $this->actingAs($other)->get($this->url)->assertNotFound();
    $this->post($this->url, $this->data)->assertNotFound();
    expect(Guardian::query()->count())->toBe(0);
    expect(LearnerGuardian::query()->count())->toBe(0);
})->with([[['parent']], [['regular_teacher']], [['system_admin', 'sped_teacher', 'school_admin']]]);

test('guests inactive and unassigned teachers cannot access guardian records', function () {
    auth()->logout();
    $this->get($this->url)->assertRedirect('/login');
    $this->post($this->url, $this->data)->assertRedirect('/login');
    foreach ([['is_active' => false], ['school_id' => null]] as $attributes) {
        $other = User::factory()->create($attributes);
        $other->assignRole('sped_teacher');
        $this->actingAs($other)->get($this->url)->assertNotFound();
        $this->post($this->url, $this->data)->assertNotFound();
    }
    $this->assertDatabaseCount('guardians', 0);
});

test('adviser reassignment immediately removes access to guardian models and endpoints', function () {
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->get($this->url)->assertNotFound();
    $this->post($this->url, $this->data)->assertNotFound();
    expect(Guardian::query()->count())->toBe(0);
    $this->actingAs($other)->get($this->url)->assertOk()->assertInertia(fn (Assert $page) => $page->has('contacts', 1));
});

test('audit failure rolls back guardian and link and does not log contact details', function () {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional private contact details'));
    $this->post($this->url, $this->data)->assertSessionHasErrors(['form' => __('guardians.save_failed')]);
    $this->assertDatabaseCount('guardians', 0);
    $this->assertDatabaseCount('learner_guardians', 0);
    $this->assertDatabaseCount('activity_log', 1);
    Log::shouldHaveReceived('error')->once()->with('Guardian creation failed', ['exception_type' => RuntimeException::class]);
});

test('audit disabled prevents guardian saving and disclosure', function () {
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    app(ActivityLogStatus::class)->disable();
    $this->get($this->url)->assertStatus(503)->assertDontSee('Fictional');
    $this->post($this->url, $this->data)->assertSessionHasErrors('form');
    $this->assertDatabaseCount('guardians', 1);
    $this->assertDatabaseCount('learner_guardians', 1);
});

test('database rejects cross school guardian and learner links', function () {
    $this->post($this->url, $this->data)->assertSessionHasNoErrors();
    $other = User::factory()->create();
    $guardian = Guardian::query()->firstOrFail();
    expect(fn () => DB::transaction(fn () => DB::table('learner_guardians')->update(['school_id' => $other->school_id])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('guardians')->where('id', $guardian->id)->update(['school_id' => $other->school_id])))->toThrow(QueryException::class);
});
