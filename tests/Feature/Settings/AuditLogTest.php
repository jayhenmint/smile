<?php

use App\Actions\AuditLearner;
use App\Models\ActivityLog;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
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

test('dual role teacher sees read only own advised audit metadata and the view is audited', function () {
    $this->get('/settings/audit-log')->assertOk()->assertInertia(fn (Assert $page) => $page->component('settings/audit-log')->has('entries.data', 1)
        ->where('entries.data.0.smile_id', $this->learner->smile_id)->where('entries.data.0.actor', $this->teacher->name)
        ->where('entries.data.0.action', __('audit_log.events.created'))->where('auditLogNav.visible', true)
        ->missing('entries.data.0.properties')->missing('entries.data.0.description')->missing('entries.data.0.subject_id')->missing('entries.data.0.lrn')->missing('entries.data.0.health_notes'));
    $this->assertDatabaseHas('activity_log', ['event' => 'audit_history_viewed', 'subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id]);
    $this->patch('/settings/audit-log')->assertStatus(405);
    $this->delete('/settings/audit-log')->assertStatus(405);
});

test('audit list and counts exclude other advisers or schools without echoing query filters', function (bool $sameSchool) {
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(['sped_teacher', 'school_admin']);
    $section = Section::factory()->create(['school_id' => $other->school_id, 'school_year_id' => SchoolYear::factory()->create(['school_id' => $other->school_id])->id, 'adviser_id' => $other->id]);
    $this->actingAs($other)->post('/learners', ['first_name' => 'Fictional hidden', 'last_name' => 'Example', 'birthdate' => '2016-01-01', 'lrn_status' => 'pending',
        'section_id' => $section->id, 'enrolled_on' => '2026-10-11', 'is_transferee' => false, 'consent_confirmed' => true])->assertSessionHasNoErrors();
    $hidden = Learner::query()->firstOrFail();
    $this->actingAs($this->teacher)->get('/settings/audit-log?school_id='.$other->school_id.'&learner_id='.$hidden->id)
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.total', 1)->where('entries.data.0.smile_id', $this->learner->smile_id));
    expect(ActivityLog::query()->where('subject_id', $hidden->id)->count())->toBe(0);
    expect(DB::table('activity_log')->where('subject_id', $hidden->id)->where('event', 'audit_history_viewed')->count())->toBe(0);
})->with([true, false]);

test('adviser reassignment removes audit access to the old learner', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole(['sped_teacher', 'school_admin']);
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->get('/settings/audit-log')->assertInertia(fn (Assert $page) => $page->has('entries.data', 0)->where('entries.total', 0));
    $this->actingAs($other)->get('/settings/audit-log')->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));
    $this->assertDatabaseHas('activity_log', ['event' => 'audit_history_viewed', 'causer_id' => $other->id]);
});

test('audit screen rejects unsupported roles inactive users and guests', function (array $roles, bool $active) {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'is_active' => $active]);
    $other->assignRole($roles);
    $this->actingAs($other)->get('/settings/audit-log')->assertForbidden();
    $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page->where('auditLogNav.visible', false));
    auth()->logout();
    $this->get('/settings/audit-log')->assertRedirect('/login');
})->with([[['school_admin'], true], [['sped_teacher'], true], [['parent'], true], [['regular_teacher'], true], [['system_admin', 'school_admin', 'sped_teacher'], true], [['school_admin', 'sped_teacher'], false]]);

test('history view fails closed when auditing is disabled or fails', function (bool $disabled) {
    if ($disabled) {
        app(ActivityLogStatus::class)->disable();
    } else {
        $this->mock(AuditLearner::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Fictional confidential history'));
    }
    $this->get('/settings/audit-log')->assertStatus(503)->assertDontSee($this->learner->smile_id);
    expect(DB::table('activity_log')->where('event', 'audit_history_viewed')->count())->toBe(0);
})->with([true, false]);

test('pagination shows twenty events and audits each distinct displayed learner once', function () {
    for ($i = 0; $i < 24; $i++) {
        app(AuditLearner::class)->record($this->teacher, $this->learner, 'viewed');
    }
    $this->get('/settings/audit-log')->assertInertia(fn (Assert $page) => $page->has('entries.data', 20)->where('entries.total', 25)->where('entries.per_page', 20));
    expect(DB::table('activity_log')->where('event', 'audit_history_viewed')->count())->toBe(1);
    $this->get('/settings/audit-log?page=2')->assertInertia(fn (Assert $page) => $page->has('entries.data', 6));
});
