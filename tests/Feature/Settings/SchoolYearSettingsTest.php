<?php

use App\Models\SchoolYear;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesSeeder::class);
});

function schoolYearInput(array $overrides = []): array
{
    return array_merge([
        'label' => '2026-2027', 'start_date' => '2026-06-08',
        'end_date' => '2027-03-31', 'is_current' => false,
    ], $overrides);
}

test('school year listing and pagination expose only the signed in school', function () {
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    SchoolYear::factory()->count(16)->create(['school_id' => $admin->school_id]);
    $other = SchoolYear::factory()->create();
    $this->actingAs($admin)->get(route('school-years.index', ['school_id' => $other->school_id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/school-years')->where('canManage', true)
        ->where('schoolYearSettingsNav.visible', true)->where('schoolYears.total', 16)
        ->has('schoolYears.data', 15)->missing('schoolYears.data.0.school_id')
        ->where('schoolYears.data', fn ($years) => collect($years)->every(fn ($year) => $year['id'] !== $other->id)));
    $this->get(route('school-years.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('schoolYears.data', 1));
});

test('administrator creates and edits calendar dates without choosing them automatically', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['school_admin', 'sped_teacher']);
    $this->actingAs($admin)->post(route('school-years.store'), schoolYearInput())
        ->assertSessionHasNoErrors()->assertRedirect(route('school-years.index'));
    $year = SchoolYear::query()->sole();
    expect($year->school_id)->toBe($admin->school_id);
    expect($year->is_current)->toBeFalse();
    $this->patch(route('school-years.update', $year), schoolYearInput([
        'label' => 'Updated year', 'start_date' => '2026-07-01', 'end_date' => '2027-04-01',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('school-years.index'));
    expect($year->fresh()->label)->toBe('Updated year');
    expect($year->fresh()->start_date->format('Y-m-d'))->toBe('2026-07-01');
    expect($year->fresh()->end_date->format('Y-m-d'))->toBe('2027-04-01');
    expect(Gate::allows('delete', $year))->toBeFalse();
});

test('selecting the current year changes only years in the same school and can be cleared', function () {
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    $old = SchoolYear::factory()->create(['school_id' => $admin->school_id, 'is_current' => true]);
    $other = SchoolYear::factory()->create(['is_current' => true]);
    $this->actingAs($admin)->post(route('school-years.store'), schoolYearInput(['is_current' => true]))
        ->assertSessionHasNoErrors();
    $new = SchoolYear::where('label', '2026-2027')->sole();
    expect($new->is_current)->toBeTrue();
    expect($old->fresh()->is_current)->toBeFalse();
    $this->assertDatabaseHas('school_years', ['id' => $other->id, 'is_current' => true]);
    $this->patch(route('school-years.update', $old), schoolYearInput(['label' => $old->label, 'is_current' => true]))
        ->assertSessionHasNoErrors();
    expect($new->fresh()->is_current)->toBeFalse();
    expect($old->fresh()->is_current)->toBeTrue();
    $this->patch(route('school-years.update', $old), schoolYearInput(['label' => $old->label]))
        ->assertSessionHasNoErrors();
    expect(SchoolYear::where('is_current', true)->count())->toBe(0);
});

test('teacher has read only calendar access and cannot create or edit years', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('sped_teacher');
    $year = SchoolYear::factory()->create(['school_id' => $teacher->school_id]);
    $this->actingAs($teacher)->get(route('school-years.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->post(route('school-years.store'), schoolYearInput())->assertForbidden();
    $this->patch(route('school-years.update', $year), schoolYearInput())->assertForbidden();
    expect($year->fresh()->label)->toBe($year->label);
    $this->assertDatabaseCount('school_years', 1);
});

test('another school year cannot be read or edited through scoped queries or policies', function () {
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    $other = SchoolYear::factory()->create(['is_current' => true]);
    $this->actingAs($admin)->patch(route('school-years.update', $other), schoolYearInput())
        ->assertNotFound();
    expect(SchoolYear::find($other->id))->toBeNull();
    expect(Gate::allows('view', $other))->toBeFalse();
    expect(Gate::allows('update', $other))->toBeFalse();
    $this->assertDatabaseHas('school_years', ['id' => $other->id, 'is_current' => true]);
});

test('unauthorized roles cannot open calendar management', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);
    if ($role === 'system_admin') {
        $user->assignRole(['school_admin', 'sped_teacher']);
    }
    $year = SchoolYear::factory()->create(['school_id' => $user->school_id]);
    $this->actingAs($user)->get(route('school-years.index'))->assertForbidden();
    $this->post(route('school-years.store'), schoolYearInput())->assertForbidden();
    $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('schoolYearSettingsNav.visible', false));
    if ($role === 'system_admin') {
        expect(SchoolYear::query()->count())->toBe(0);
    }
    expect(Gate::allows('update', $year))->toBeFalse();
})->with(['parent', 'regular_teacher', 'system_admin']);

test('guests inactive accounts and administrators without membership cannot access calendars', function () {
    SchoolYear::factory()->create();
    $this->get(route('school-years.index'))->assertRedirect(route('login'));
    $this->post(route('school-years.store'), schoolYearInput())->assertRedirect(route('login'));
    expect(SchoolYear::query()->count())->toBe(0);
    foreach ([['is_active' => false], ['school_id' => null]] as $attributes) {
        $admin = User::factory()->create($attributes);
        $admin->assignRole('school_admin');
        $this->actingAs($admin)->get(route('school-years.index'))->assertForbidden();
        $this->post(route('school-years.store'), schoolYearInput())->assertForbidden();
        expect(SchoolYear::query()->count())->toBe(0);
    }
    $this->assertDatabaseCount('school_years', 1);
});

test('year validation rejects invalid dates flags and school or record injection', function (array $input, string $error) {
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id, 'is_current' => true]);
    $this->actingAs($admin)->post(route('school-years.store'), schoolYearInput($input))
        ->assertSessionHasErrors($error);
    $this->patch(route('school-years.update', $year), schoolYearInput($input))
        ->assertSessionHasErrors($error);
    expect($year->fresh()->is_current)->toBeTrue();
    expect($year->fresh()->label)->toBe($year->label);
    $this->assertDatabaseCount('school_years', 1);
})->with([
    [['label' => ''], 'label'],
    [['start_date' => '2026-02-30'], 'start_date'],
    [['end_date' => '2026-01-01'], 'end_date'],
    [['start_date' => '06/08/2026'], 'start_date'],
    [['is_current' => 'current'], 'is_current'],
    [['is_current' => null], 'is_current'],
    [['school_id' => 999], 'school_id'],
    [['id' => 999], 'id'],
]);

test('duplicate labels are rejected within a school but allowed in another school', function () {
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    SchoolYear::factory()->create(['school_id' => $admin->school_id, 'label' => '2026-2027']);
    $other = SchoolYear::factory()->create(['label' => 'Shared year']);
    $this->actingAs($admin)->post(route('school-years.store'), schoolYearInput())
        ->assertSessionHasErrors('label');
    $this->post(route('school-years.store'), schoolYearInput(['label' => $other->label]))
        ->assertSessionHasNoErrors();
    $this->assertDatabaseCount('school_years', 3);
});

test('database prevents duplicate current years and duplicate school year labels', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);
    SchoolYear::factory()->create(['school_id' => $admin->school_id, 'label' => 'Existing year', 'is_current' => true]);
    expect(fn () => DB::transaction(fn () => SchoolYear::factory()->create([
        'school_id' => $admin->school_id, 'is_current' => true,
    ])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => SchoolYear::factory()->create([
        'school_id' => $admin->school_id, 'label' => 'Existing year',
    ])))->toThrow(QueryException::class);
    expect(SchoolYear::query()->count())->toBe(1);
});
