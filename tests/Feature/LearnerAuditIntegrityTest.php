<?php

use App\Actions\AuditLearner;
use App\Models\ActivityLog;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;

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

test('learner audit has exact school actor subject event and no identifying attributes', function () {
    app(AuditLearner::class)->record($this->teacher, $this->learner, 'viewed');
    $row = DB::table('activity_log')->where('event', 'viewed')->first();
    expect((int) $row->school_id)->toBe($this->teacher->school_id);
    expect($row->causer_id)->toBe($this->teacher->id);
    expect($row->causer_type)->toBe($this->teacher->getMorphClass());
    expect($row->subject_id)->toBe($this->learner->id);
    expect($row->subject_type)->toBe($this->learner->getMorphClass());
    expect($row->description)->toBe('learner.viewed');
    expect(json_decode($row->properties, true))->toBe([]);
    expect(json_encode($row))->not->toContain('Fictional', 'health', 'birthdate');
});

test('audit helper rejects actors from another school or without membership', function (bool $assigned) {
    $actor = User::factory()->create($assigned ? [] : ['school_id' => null]);
    $actor->assignRole('sped_teacher');
    $before = DB::table('activity_log')->count();
    expect(fn () => app(AuditLearner::class)->record($actor, $this->learner, 'viewed'))->toThrow(RuntimeException::class, 'Invalid learner audit context.');
    expect(DB::table('activity_log')->count())->toBe($before);
})->with([true, false]);

test('audit helper rejects unsaved subjects or actors', function (bool $subject) {
    $before = DB::table('activity_log')->count();
    $actor = $subject ? $this->teacher : new User(['school_id' => $this->teacher->school_id]);
    $learner = $subject ? new Learner : $this->learner;
    expect(fn () => app(AuditLearner::class)->record($actor, $learner, 'viewed'))->toThrow(RuntimeException::class, 'Invalid learner audit context.');
    expect(DB::table('activity_log')->count())->toBe($before);
})->with([true, false]);

test('unrecognized or private event descriptions cannot enter the audit log', function (string $event) {
    $before = DB::table('activity_log')->count();
    try {
        app(AuditLearner::class)->record($this->teacher, $this->learner, $event);
        $this->fail('Unsupported event was accepted');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Invalid learner audit context.')->not->toContain($event);
    }
    expect(DB::table('activity_log')->count())->toBe($before);
})->with(['Fictional diagnosis detail', 'deleted', 'exported', 'VIEWED']);

test('audit queries cannot read another schools entries and technical operators have no routine audit access', function () {
    app(AuditLearner::class)->record($this->teacher, $this->learner, 'viewed');
    $other = User::factory()->create();
    $other->assignRole(['sped_teacher', 'school_admin']);
    $this->actingAs($other);
    expect(ActivityLog::query()->count())->toBe(0);
    $operator = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $operator->assignRole(['system_admin', 'sped_teacher', 'school_admin']);
    $this->actingAs($operator);
    expect(ActivityLog::query()->count())->toBe(0);
    auth()->logout();
    expect(ActivityLog::query()->count())->toBe(0);
});

test('invalid audit context rolls back the caller transaction', function () {
    $original = $this->learner->first_name;
    $other = User::factory()->create();
    expect(fn () => DB::transaction(function () use ($other) {
        DB::table('learners')->where('id', $this->learner->id)->update(['first_name' => 'Fictional unsaved change']);
        app(AuditLearner::class)->record($other, $this->learner, 'updated');
    }))->toThrow(RuntimeException::class);
    expect(DB::table('learners')->where('id', $this->learner->id)->value('first_name'))->toBe($original);
    expect(DB::table('activity_log')->where('event', 'updated')->count())->toBe(0);
});
