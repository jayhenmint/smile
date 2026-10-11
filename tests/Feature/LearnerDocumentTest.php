<?php

use App\Actions\AuditLearner;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
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

beforeEach(function () {
    $this->docUrl = $this->url.'/documents';
    $this->docData = ['status' => 'submitted', 'submitted_on' => '2026-10-11', 'notes' => 'Fictional confidential medical note'];
});

test('checklist starts missing without creating records and permits paper submission verification and corrections', function () {
    $original = (array) DB::table('learners')->first();
    $enrollment = (array) DB::table('enrollments')->first();
    $this->get($this->docUrl)->assertOk()->assertInertia(fn (Assert $page) => $page->component('learners/documents')->has('items', 17)
        ->where('items.0.status', 'missing')->where('learner.smile_id', $this->learner->smile_id)->missing('learner.health_notes')->missing('learner.birthdate')->missing('learner.lrn')->missing('items.0.file_url'));
    $this->assertDatabaseCount('learner_documents', 0);
    $this->assertDatabaseHas('activity_log', ['event' => 'documents_viewed', 'subject_id' => $this->learner->id]);
    $this->put($this->docUrl.'/4', $this->docData)->assertRedirect($this->docUrl)->assertSessionHas('document_saved', true);
    $this->assertDatabaseHas('learner_documents', ['school_id' => $this->teacher->school_id, 'learner_id' => $this->learner->id,
        'document_type_id' => 4, 'status' => 'submitted', 'verified_by' => null, 'file_url' => null]);
    $record = LearnerDocument::query()->firstOrFail();
    expect($record->notes)->toBe($this->docData['notes']);
    expect(DB::table('learner_documents')->value('notes'))->not->toContain('Fictional');
    expect($record->toArray())->not->toHaveKeys(['notes', 'file_url']);
    $this->get($this->docUrl)->assertInertia(fn (Assert $page) => $page->where('items.3.notes', $this->docData['notes'])->where('items.3.submitted_on', '2026-10-11'));
    $this->put($this->docUrl.'/4', [...$this->docData, 'status' => 'verified'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learner_documents', ['verified_by' => $this->teacher->id, 'status' => 'verified']);
    $this->put($this->docUrl.'/4', [...$this->docData, 'status' => 'expired'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learner_documents', ['verified_by' => null, 'status' => 'expired']);
    $this->put($this->docUrl.'/4', ['status' => 'missing', 'submitted_on' => '', 'notes' => ''])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learner_documents', ['status' => 'missing', 'submitted_on' => null, 'verified_by' => null, 'notes' => null]);
    $this->assertDatabaseCount('learner_documents', 1);
    expect((array) DB::table('learners')->first())->toBe($original);
    expect((array) DB::table('enrollments')->first())->toBe($enrollment);
    $this->assertDatabaseHas('activity_log', ['event' => 'document_checklist_updated', 'subject_id' => $this->learner->id, 'causer_id' => $this->teacher->id]);
    expect(json_encode(DB::table('activity_log')->get()))->not->toContain('Fictional', 'confidential');
});

test('document payload cannot inject ownership verification attribution or attachments', function (string $field) {
    $this->put($this->docUrl.'/1', [...$this->docData, $field => 'untrusted'])->assertSessionHasErrors($field)->assertSessionMissing('_old_input.notes');
    $this->assertDatabaseCount('learner_documents', 0);
})->with(['id', 'school_id', 'learner_id', 'document_type_id', 'verified_by', 'file_url', 'file', 'created_at']);

test('empty protected inputs cannot override server document ownership or verifier', function () {
    $this->put($this->docUrl.'/1', [...$this->docData, 'status' => 'verified', 'school_id' => null, 'learner_id' => null, 'document_type_id' => null, 'verified_by' => null, 'file_url' => null])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learner_documents', ['school_id' => $this->teacher->school_id, 'learner_id' => $this->learner->id, 'document_type_id' => 1, 'verified_by' => $this->teacher->id, 'file_url' => null]);
});

test('invalid statuses dates or notes are rejected without flashing private notes', function (array $change, string $field) {
    $this->put($this->docUrl.'/1', [...$this->docData, ...$change])->assertSessionHasErrors($field)->assertSessionMissing('_old_input.notes');
    $this->assertDatabaseCount('learner_documents', 0);
})->with([[['status' => 'approved'], 'status'], [['status' => null], 'status'], [['submitted_on' => null], 'submitted_on'], [['submitted_on' => 'bad'], 'submitted_on'], [['submitted_on' => '2099-01-01'], 'submitted_on'], [['status' => 'missing'], 'submitted_on'], [['notes' => ['bad']], 'notes'], [['notes' => str_repeat('x', 10001)], 'notes']]);

test('unknown document types are rejected', function () {
    $this->put($this->docUrl.'/99999', $this->docData)->assertNotFound();
    $this->assertDatabaseCount('learner_documents', 0);
});

test('other advisers schools and unsupported roles cannot read or save checklist records', function (string $roles, bool $sameSchool) {
    $this->put($this->docUrl.'/4', $this->docData)->assertSessionHasNoErrors();
    $other = User::factory()->create($sameSchool ? ['school_id' => $this->teacher->school_id] : []);
    $other->assignRole(explode(',', $roles));
    $this->actingAs($other)->get($this->docUrl)->assertNotFound();
    $this->put($this->docUrl.'/4', [...$this->docData, 'status' => 'verified'])->assertNotFound();
    expect(LearnerDocument::query()->count())->toBe(0);
    $this->assertDatabaseHas('learner_documents', ['status' => 'submitted', 'verified_by' => null]);
    expect(DB::table('activity_log')->where('event', 'document_checklist_updated')->count())->toBe(1);
})->with([['sped_teacher,school_admin', true], ['sped_teacher,school_admin', false], ['parent', true], ['regular_teacher', true], ['school_admin', true], ['system_admin,sped_teacher,school_admin', true]]);

test('reassignment transfers checklist access without allowing the old adviser to update', function () {
    $this->put($this->docUrl.'/4', $this->docData)->assertSessionHasNoErrors();
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $other->assignRole('sped_teacher');
    DB::table('sections')->where('id', $this->section->id)->update(['adviser_id' => $other->id]);
    $this->get($this->docUrl)->assertNotFound();
    $this->put($this->docUrl.'/4', [...$this->docData, 'status' => 'verified'])->assertNotFound();
    $this->actingAs($other)->get($this->docUrl)->assertOk();
    $this->put($this->docUrl.'/4', [...$this->docData, 'status' => 'verified'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('learner_documents', ['verified_by' => $other->id]);
});

test('inactive users and guests cannot access the checklist', function () {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id, 'is_active' => false]);
    $other->assignRole('sped_teacher');
    $this->actingAs($other)->get($this->docUrl)->assertNotFound();
    $this->put($this->docUrl.'/1', $this->docData)->assertNotFound();
    auth()->logout();
    $this->get($this->docUrl)->assertRedirect('/login');
    $this->put($this->docUrl.'/1', $this->docData)->assertRedirect('/login');
    $this->assertDatabaseCount('learner_documents', 0);
});

test('audit failure prevents viewing and rolls back checklist creation or editing', function (bool $existing) {
    if ($existing) {
        $this->put($this->docUrl.'/4', $this->docData)->assertSessionHasNoErrors();
    }
    $before = DB::table('learner_documents')->get()->toJson();
    Log::spy();
    $this->mock(AuditLearner::class)->shouldReceive('record')->twice()->andThrow(new RuntimeException('Fictional confidential medical note'));
    $this->get($this->docUrl)->assertStatus(503);
    $this->put($this->docUrl.'/4', [...$this->docData, 'status' => 'verified'])->assertSessionHasErrors('form');
    expect(DB::table('learner_documents')->get()->toJson())->toBe($before);
    Log::shouldHaveReceived('error')->with('Document checklist unavailable', ['exception_type' => RuntimeException::class])->once();
    Log::shouldHaveReceived('error')->with('Document checklist save failed', ['exception_type' => RuntimeException::class])->once();
})->with([true, false]);

test('disabled auditing prevents reading or saving the checklist', function () {
    app(ActivityLogStatus::class)->disable();
    $this->get($this->docUrl)->assertStatus(503);
    $this->put($this->docUrl.'/1', $this->docData)->assertSessionHasErrors('form');
    $this->assertDatabaseCount('learner_documents', 0);
});

test('database rejects duplicate checklist states and cross school ownership or verifier references', function (string $kind) {
    $this->put($this->docUrl.'/1', $this->docData)->assertSessionHasNoErrors();
    $other = User::factory()->create();
    $row = ['school_id' => $this->teacher->school_id, 'learner_id' => $this->learner->id, 'document_type_id' => 2, 'status' => 'verified', 'verified_by' => null];
    if ($kind === 'duplicate') {
        $row['document_type_id'] = 1;
    }
    if ($kind === 'school') {
        $row['school_id'] = $other->school_id;
    }
    if ($kind === 'verifier') {
        $row['verified_by'] = $other->id;
    }
    expect(fn () => DB::transaction(fn () => DB::table('learner_documents')->insert($row)))->toThrow(QueryException::class);
})->with(['duplicate', 'school', 'verifier']);
