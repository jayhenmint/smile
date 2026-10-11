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

test('own adviser can open audited detail and edit screens with only intended profile fields', function (string $suffix, bool $editing) {
    $this->get($this->url.$suffix)->assertOk()->assertInertia(fn (Assert $page) => $page->component('learners/profile')->where('editing', $editing)
        ->where('learner.smile_id', $this->learner->smile_id)->where('learner.lrn', '001234567890')
        ->where('learner.health_notes', 'Fictional initial health note')->where('learner.birthdate', '2015-05-03')
        ->where('learner.enrollments.0.section', $this->section->name)->missing('learner.school_id')->missing('learner.photo_url')->missing('learner.pwd_id_number'));
    $this->assertDatabaseHas('activity_log', ['event' => 'viewed', 'subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id]);
})->with([['', false], ['/edit', true]]);

test('identity update preserves identifiers consent and enrollment and encrypts new health notes', function () {
    $enrollment = (array) DB::table('enrollments')->first();
    $consent = $this->learner->consent_confirmed_at->toIso8601String();
    $this->patch($this->url, $this->data)->assertRedirect($this->url)->assertSessionHas('learner_updated', true);
    $learner = Learner::query()->firstOrFail();
    expect($learner->first_name)->toBe('Fictional Updated');
    expect($learner->health_notes)->toBe($this->data['health_notes']);
    expect($learner->smile_id)->toBe($this->learner->smile_id);
    expect($learner->lrn)->toBe('001234567890');
    expect($learner->consent_confirmed_at->toIso8601String())->toBe($consent);
    expect((array) DB::table('enrollments')->first())->toBe($enrollment);
    expect(DB::table('learners')->value('health_notes'))->not->toContain('Fictional');
    $this->assertDatabaseCount('smile_id_reservations', 1);
    $this->assertDatabaseHas('activity_log', ['event' => 'updated', 'subject_id' => $learner->id]);
    expect(json_encode(DB::table('activity_log')->get()))->not->toContain('Fictional', '001234567890');
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('learner.first_name', 'Fictional Updated')->where('learner.health_notes', $this->data['health_notes']));
});

test('identity validation rejects invalid data without logging or flashing sensitive input', function (string $field, $value) {
    $this->patch($this->url, [...$this->data, $field => $value])->assertSessionHasErrors($field)->assertSessionMissing('_old_input.first_name')->assertSessionMissing('_old_input.health_notes');
    $this->assertDatabaseHas('learners', ['first_name' => 'Fictional']);
    $this->assertDatabaseCount('activity_log', 1);
})->with([['first_name', ''], ['birthdate', '2099-01-01'], ['sex', 'invalid'], ['health_notes', str_repeat('a', 5001)]]);

test('identity screen rejects identifier school enrollment and consent tampering', function (string $field) {
    $this->patch($this->url, [...$this->data, $field => 'untrusted'])->assertSessionHasErrors($field);
    $this->assertDatabaseHas('learners', ['first_name' => 'Fictional', 'lrn' => '001234567890']);
})->with(['smile_id', 'lrn', 'lrn_status', 'school_id', 'section_id', 'level_status', 'grade_level', 'status', 'consent_confirmed_by', 'consent_confirmed_at']);

test('empty prohibited fields cannot clear an existing LRN or status through the update allowlist', function () {
    $this->patch($this->url, [...$this->data, 'lrn' => null, 'lrn_status' => '', 'smile_id' => null])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learners', ['lrn' => '001234567890', 'lrn_status' => 'issued', 'smile_id' => $this->learner->smile_id]);
});

test('other adviser and school cannot open or update a learner even with school admin access', function (bool $sameSchool) {
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(['sped_teacher', 'school_admin']);
    $this->actingAs($other)->get($this->url)->assertNotFound();
    $this->get($this->url.'/edit')->assertNotFound();
    $this->patch($this->url, $this->data)->assertNotFound();
    $this->assertDatabaseHas('learners', ['first_name' => 'Fictional']);
    $this->assertDatabaseCount('activity_log', 1);
})->with([true, false]);

test('unsupported roles cannot read or edit profiles', function (array $roles) {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole($roles);
    $this->actingAs($other)->get($this->url)->assertNotFound();
    $this->get($this->url.'/edit')->assertNotFound();
    $this->patch($this->url, $this->data)->assertNotFound();
})->with([[['parent']], [['regular_teacher']], [['school_admin']], [['system_admin', 'sped_teacher', 'school_admin']]]);

test('reassignment removes edit access immediately and guests and inactive accounts cannot open profiles', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->patch($this->url, $this->data)->assertNotFound();
    $this->actingAs($other)->get($this->url.'/edit')->assertOk();
    $other->is_active = false;
    $other->save();
    $this->get($this->url)->assertNotFound();
    $this->patch($this->url, $this->data)->assertNotFound();
    auth()->logout();
    $this->get($this->url)->assertRedirect('/login');
    $this->patch($this->url, $this->data)->assertRedirect('/login');
});

test('audit failure rolls back identity changes and sanitizes error logs', function () {
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional private health note'));
    $this->patch($this->url, $this->data)->assertSessionHasErrors('form');
    expect(Learner::query()->firstOrFail()->health_notes)->toBe('Fictional initial health note');
    $this->assertDatabaseHas('learners', ['first_name' => 'Fictional']);
    $this->assertDatabaseCount('activity_log', 1);
    Log::shouldHaveReceived('error')->once()->with('Learner update failed', ['exception_type' => RuntimeException::class]);
});

test('disabled auditing blocks profile detail edit delivery and updating', function () {
    app(ActivityLogStatus::class)->disable();
    $this->get($this->url)->assertStatus(503)->assertDontSee('Fictional');
    $this->get($this->url.'/edit')->assertStatus(503);
    $this->patch($this->url, $this->data)->assertSessionHasErrors('form');
    $this->assertDatabaseHas('learners', ['first_name' => 'Fictional']);
});
