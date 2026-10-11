<?php

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionTeacher;
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

function sectionAdministrator(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(['school_admin', 'sped_teacher']);

    return $admin;
}

function sectionInput(SchoolYear $year, array $overrides = []): array
{
    return array_merge([
        'school_year_id' => $year->id, 'name' => 'Example SNEd class',
        'placement_type' => 'sped_self_contained', 'adviser_id' => null,
    ], $overrides);
}

test('section setup choices and pagination stay within the administrator school', function () {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    Section::factory()->count(16)->create(['school_year_id' => $year->id]);
    $other = Section::factory()->create();
    $inactive = User::factory()->create(['school_id' => $admin->school_id, 'is_active' => false]);
    $inactive->assignRole('sped_teacher');
    $system = User::factory()->create(['school_id' => $admin->school_id]);
    $system->assignRole(['system_admin', 'sped_teacher']);
    $this->actingAs($admin)->get(route('sections.index', ['school_id' => $other->school_id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/sections')->where('canManage', true)->where('sectionSettingsNav.visible', true)
        ->where('sections.total', 16)->has('sections.data', 15)->has('schoolYears', 1)->has('advisers', 1)
        ->where('advisers.0.id', $admin->id)->missing('advisers.0.password')
        ->where('sections.data', fn ($sections) => collect($sections)->every(fn ($section) => $section['id'] !== $other->id)));
    $this->get(route('sections.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('sections.data', 1));
});

test('administrator creates non graded sections and synchronizes adviser assignment', function () {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $this->actingAs($admin)->post(route('sections.store'), sectionInput($year, ['adviser_id' => $admin->id]))
        ->assertSessionHasNoErrors()->assertRedirect(route('sections.index'));
    $section = Section::query()->sole();
    expect($section->school_id)->toBe($admin->school_id);
    expect($section->school_year_id)->toBe($year->id);
    expect($section->grade_level)->toBe('Non-graded');
    expect($section->adviser->id)->toBe($admin->id);
    expect($year->sections()->sole()->id)->toBe($section->id);
    expect($section->teachers()->sole()->pivot->role)->toBe('adviser');
    expect(SectionTeacher::query()->sole()->school_id)->toBe($admin->school_id);
    expect(Gate::allows('view', SectionTeacher::query()->sole()))->toBeTrue();
    $this->assertDatabaseHas('section_teachers', [
        'school_id' => $admin->school_id, 'section_id' => $section->id, 'user_id' => $admin->id, 'role' => 'adviser',
    ]);
    expect(Gate::allows('delete', $section))->toBeFalse();
});

test('reassigning or clearing the adviser replaces only the adviser pivot row', function () {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $new = User::factory()->create(['school_id' => $admin->school_id]);
    $new->assignRole('sped_teacher');
    $coTeacher = User::factory()->create(['school_id' => $admin->school_id]);
    $this->actingAs($admin)->post(route('sections.store'), sectionInput($year, ['adviser_id' => $admin->id]));
    $section = Section::query()->sole();
    DB::table('section_teachers')->insert(['school_id' => $admin->school_id, 'section_id' => $section->id, 'user_id' => $coTeacher->id, 'role' => 'co_teacher']);
    $this->patch(route('sections.update', $section), [
        'name' => 'Updated class', 'placement_type' => 'resource_room', 'adviser_id' => $new->id,
    ])->assertSessionHasNoErrors()->assertRedirect(route('sections.index'));
    expect($section->fresh()->adviser_id)->toBe($new->id);
    expect($section->fresh()->grade_level)->toBe('Non-graded');
    $this->assertDatabaseMissing('section_teachers', ['section_id' => $section->id, 'user_id' => $admin->id]);
    $this->assertDatabaseHas('section_teachers', ['section_id' => $section->id, 'user_id' => $new->id, 'role' => 'adviser']);
    $this->assertDatabaseHas('section_teachers', ['section_id' => $section->id, 'user_id' => $coTeacher->id, 'role' => 'co_teacher']);
    $this->patch(route('sections.update', $section), ['name' => 'Updated class', 'placement_type' => 'regular', 'adviser_id' => null])
        ->assertSessionHasNoErrors();
    expect($section->fresh()->adviser_id)->toBeNull();
    expect($section->fresh()->grade_level)->toBe('Non-graded');
    $this->assertDatabaseMissing('section_teachers', ['section_id' => $section->id, 'role' => 'adviser']);
    $this->assertDatabaseHas('section_teachers', ['section_id' => $section->id, 'user_id' => $coTeacher->id]);
});

test('a teacher sees only their advised sections and cannot edit any section', function () {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $teacher = User::factory()->create(['school_id' => $admin->school_id]);
    $teacher->assignRole('sped_teacher');
    $own = Section::factory()->create(['school_year_id' => $year->id, 'adviser_id' => $teacher->id]);
    $other = Section::factory()->create(['school_year_id' => $year->id, 'adviser_id' => $admin->id]);
    DB::table('section_teachers')->insert(['school_id' => $admin->school_id, 'section_id' => $other->id, 'user_id' => $teacher->id, 'role' => 'co_teacher']);
    $this->actingAs($teacher)->get(route('sections.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canManage', false)->has('sections.data', 1)->where('sections.data.0.id', $own->id)
        ->has('advisers', 0)->has('schoolYears', 0));
    expect(Section::find($other->id))->toBeNull();
    expect(Gate::allows('view', $other))->toBeFalse();
    expect(Gate::allows('view', $own))->toBeTrue();
    expect(SectionTeacher::query()->count())->toBe(0);
    $this->patch(route('sections.update', $other), ['name' => 'Tampered', 'placement_type' => 'regular'])->assertNotFound();
    $this->patch(route('sections.update', $own), ['name' => 'Tampered', 'placement_type' => 'regular'])->assertForbidden();
    $this->post(route('sections.store'), sectionInput($year))->assertForbidden();
    $this->assertDatabaseHas('sections', ['id' => $other->id, 'name' => $other->name]);
});

test('cross school years advisers and section updates are rejected', function () {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $otherYear = SchoolYear::factory()->create();
    $otherTeacher = User::factory()->create(['school_id' => $otherYear->school_id]);
    $otherTeacher->assignRole('sped_teacher');
    $otherSection = Section::factory()->create(['school_year_id' => $otherYear->id]);
    $this->actingAs($admin)->post(route('sections.store'), sectionInput($otherYear))
        ->assertSessionHasErrors('school_year_id');
    $this->post(route('sections.store'), sectionInput($year, ['adviser_id' => $otherTeacher->id]))
        ->assertSessionHasErrors('adviser_id');
    $this->patch(route('sections.update', $otherSection), ['name' => 'Tampered', 'placement_type' => 'regular'])->assertNotFound();
    expect(Gate::allows('view', $otherSection))->toBeFalse();
    expect(Section::query()->count())->toBe(0);
    $this->assertDatabaseCount('sections', 1);
});

test('inactive or non teacher accounts cannot be advisers', function (string $role, bool $active) {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $target = User::factory()->create(['school_id' => $admin->school_id, 'is_active' => $active]);
    $target->assignRole($role);
    if ($role === 'system_admin') {
        $target->assignRole('sped_teacher');
    }
    $this->actingAs($admin)->post(route('sections.store'), sectionInput($year, ['adviser_id' => $target->id]))
        ->assertSessionHasErrors('adviser_id');
    $this->assertDatabaseCount('sections', 0);
    $this->assertDatabaseCount('section_teachers', 0);
})->with([['sped_teacher', false], ['school_admin', true], ['parent', true], ['regular_teacher', true], ['system_admin', true]]);

test('unsupported input cannot change section ownership grade or assignment', function (array $extra, string $error) {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $this->actingAs($admin)->post(route('sections.store'), sectionInput($year, $extra))->assertSessionHasErrors($error);
    $this->assertDatabaseCount('sections', 0);
})->with([
    [['school_id' => 999], 'school_id'], [['id' => 999], 'id'], [['grade_level' => 'Grade 1'], 'grade_level'],
    [['placement_type' => 'invalid'], 'placement_type'], [['name' => ''], 'name'],
    [['adviser_id' => 'invalid-id'], 'adviser_id'],
]);

test('section year is immutable and duplicate section names are scoped to school year', function () {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $another = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $this->actingAs($admin)->post(route('sections.store'), sectionInput($year))->assertSessionHasNoErrors();
    $section = Section::query()->sole();
    $this->post(route('sections.store'), sectionInput($year))->assertSessionHasErrors('name');
    $this->post(route('sections.store'), sectionInput($another))->assertSessionHasNoErrors();
    $this->patch(route('sections.update', $section), sectionInput($another))->assertSessionHasErrors('school_year_id');
    $this->patch(route('sections.update', $section), ['name' => 'Changed', 'placement_type' => 'regular', 'grade_level' => 'Grade 1'])
        ->assertSessionHasErrors('grade_level');
    expect($section->fresh()->school_year_id)->toBe($year->id);
    expect($section->fresh()->grade_level)->toBe('Non-graded');
});

test('guests inactive users and unsupported roles cannot read section metadata', function () {
    $this->get(route('sections.index'))->assertRedirect(route('login'));
    expect(Section::query()->count())->toBe(0);
    foreach (['parent', 'regular_teacher', 'system_admin'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        if ($role === 'system_admin') {
            $user->assignRole(['school_admin', 'sped_teacher']);
        }
        Section::factory()->create(['school_year_id' => SchoolYear::factory()->create(['school_id' => $user->school_id])->id]);
        $this->actingAs($user)->get(route('sections.index'))->assertForbidden();
        expect(Section::query()->count())->toBe(0);
        expect(SectionTeacher::query()->count())->toBe(0);
        $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('sectionSettingsNav.visible', false));
    }
    $inactive = User::factory()->create(['is_active' => false]);
    $inactive->assignRole('school_admin');
    $this->actingAs($inactive)->get(route('sections.index'))->assertForbidden();
    expect(Section::query()->count())->toBe(0);
});

test('database rejects cross school section and staff links', function () {
    $admin = sectionAdministrator();
    $year = SchoolYear::factory()->create(['school_id' => $admin->school_id]);
    $other = User::factory()->create();
    $otherYear = SchoolYear::factory()->create(['school_id' => $other->school_id]);
    $this->actingAs($admin);
    expect(fn () => DB::transaction(fn () => Section::factory()->create([
        'school_id' => $admin->school_id, 'school_year_id' => $otherYear->id,
    ])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => Section::factory()->create([
        'school_year_id' => $year->id, 'adviser_id' => $other->id,
    ])))->toThrow(QueryException::class);
    $section = Section::factory()->create(['school_year_id' => $year->id]);
    expect(fn () => DB::transaction(fn () => DB::table('section_teachers')->insert([
        'school_id' => $admin->school_id, 'section_id' => $section->id, 'user_id' => $other->id, 'role' => 'co_teacher',
    ])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('section_teachers')->insert([
        'school_id' => $other->school_id, 'section_id' => $section->id, 'user_id' => $other->id, 'role' => 'co_teacher',
    ])))->toThrow(QueryException::class);
    $this->assertDatabaseCount('section_teachers', 0);
});
