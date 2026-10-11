<?php

use App\Models\School;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesSeeder::class);
});

test('school administrator sees only their own school settings', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole(['school_admin', 'sped_teacher']);
    School::factory()->create(['name' => 'Other School']);

    $this->actingAs($teacher)->get(route('school.edit'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/school')
        ->where('school.name', $teacher->school->name)
        ->where('schoolSettingsNav.visible', true)
        ->where('labels.title', 'School settings')
        ->missing('school.users'));
});

test('school administrator updates their school and preserves a leading zero school id', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('school_admin');
    $this->actingAs($teacher)->patch(route('school.update'), [
        'name' => 'Updated Example School', 'deped_school_id' => '012345',
        'division' => 'Example Division', 'region' => 'Example Region', 'address' => 'Example Address',
    ])->assertSessionHasNoErrors()->assertRedirect(route('school.edit'));
    $this->assertDatabaseHas('schools', [
        'id' => $teacher->school_id, 'name' => 'Updated Example School', 'deped_school_id' => '012345',
    ]);
    // Keeping the existing unique ID is valid; clearing optional fields is supported.
    $this->patch(route('school.update'), [
        'name' => 'Updated Example School', 'deped_school_id' => '012345',
        'division' => '', 'region' => '', 'address' => '',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('schools', ['id' => $teacher->school_id, 'division' => null, 'region' => null, 'address' => null]);
    $this->patch(route('school.update'), ['name' => 'Updated Example School', 'deped_school_id' => ''])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('schools', ['id' => $teacher->school_id, 'deped_school_id' => null]);
});

test('school selection and noneditable fields cannot be supplied by the client', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('school_admin');
    $other = School::factory()->create();
    $this->actingAs($teacher)->patch(route('school.update'), [
        'name' => 'Own Updated School', 'id' => $other->id, 'school_id' => $other->id, 'created_at' => '2000-01-01',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('schools', ['id' => $teacher->school_id, 'name' => 'Own Updated School']);
    $this->assertDatabaseHas('schools', ['id' => $other->id, 'name' => $other->name]);
    expect($teacher->school->created_at->year)->not->toBe(2000);
    $this->get(route('school.edit', ['school_id' => $other->id]))
        ->assertInertia(fn (Assert $page) => $page->where('school.name', 'Own Updated School'));
});

test('nonadministrators cannot view or update school settings', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user)->get(route('school.edit'))->assertForbidden();
    $this->patch(route('school.update'), ['name' => 'Unauthorized'])->assertForbidden();
    $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('schoolSettingsNav.visible', false));
    $this->assertDatabaseMissing('schools', ['name' => 'Unauthorized']);
})->with(['sped_teacher', 'regular_teacher', 'parent']);

test('guests cannot open or change school settings', function () {
    $this->get(route('school.edit'))->assertRedirect(route('login'));
    $this->patch(route('school.update'), ['name' => 'Unauthorized'])->assertRedirect(route('login'));
});

test('unassigned and inactive administrators cannot open school settings', function () {
    $unassigned = User::factory()->create(['school_id' => null]);
    $unassigned->assignRole('school_admin');
    $this->actingAs($unassigned)->get(route('school.edit'))->assertNotFound();
    $this->patch(route('school.update'), ['name' => 'Unauthorized'])->assertNotFound();

    $inactive = User::factory()->create(['is_active' => false]);
    $inactive->assignRole('school_admin');
    $this->actingAs($inactive)->get(route('school.edit'))->assertNotFound();
    $this->patch(route('school.update'), ['name' => 'Unauthorized'])->assertNotFound();
});

test('invalid and globally duplicate school details do not modify the school', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('school_admin');
    School::factory()->create(['deped_school_id' => '123456']);
    $this->actingAs($teacher);
    foreach (['123456', '12345', '1234567', 'abcdef', '12.345', 123456] as $invalid) {
        $this->patch(route('school.update'), ['name' => 'Rejected Change', 'deped_school_id' => $invalid])
            ->assertSessionHasErrors('deped_school_id');
    }
    $this->patch(route('school.update'), ['name' => '', 'division' => str_repeat('x', 256), 'address' => str_repeat('x', 2001)])
        ->assertSessionHasErrors(['name', 'division', 'address']);
    $this->assertDatabaseMissing('schools', ['name' => 'Rejected Change']);
});
