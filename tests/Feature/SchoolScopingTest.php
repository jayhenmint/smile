<?php

use App\Models\School;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

test('ordinary user queries read only the authenticated school', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $adviser = User::factory()->for($schoolA)->create();
    $colleague = User::factory()->for($schoolA)->create();
    $other = User::factory()->for($schoolB)->create();

    $this->actingAs($adviser);

    expect(User::query()->count())->toBe(2);
    expect(User::find($other->id))->toBeNull();
    expect(User::find($colleague->id)->id)->toBe($colleague->id);
    expect(School::query()->pluck('id')->all())->toBe([$schoolA->id]);
    expect(School::find($schoolB->id))->toBeNull();
    expect($adviser->school->id)->toBe($schoolA->id);
    expect($schoolB->users()->count())->toBe(0);
});

test('joins and or conditions do not bypass school scoping', function () {
    $adviser = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($adviser);

    expect(User::where('id', $adviser->id)->orWhere('id', $other->id)->pluck('id')->all())
        ->toBe([$adviser->id]);
    expect(User::query()->join('schools', 'schools.id', '=', 'users.school_id')->pluck('users.id')->all())
        ->toBe([$adviser->id]);
});

test('scoped bulk updates cannot change another school account', function () {
    $adviser = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($adviser);

    expect(User::whereKey($other->id)->update(['name' => 'Changed']))->toBe(0);
    expect(User::whereKey($other->id)->delete())->toBe(0);
    $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => $other->name]);
});

test('guests and accounts without a school have no ordinary data access', function () {
    User::factory()->create();
    $unassigned = User::factory()->create(['school_id' => null]);

    expect(User::query()->count())->toBe(0);
    expect(School::query()->count())->toBe(0);
    $this->actingAs($unassigned);
    expect(User::query()->count())->toBe(0);
    expect(School::query()->count())->toBe(0);
});

test('system admin sees school metadata but no school-owned accounts', function () {
    $this->seed(RolesSeeder::class);
    $teacher = User::factory()->create();
    $operator = User::factory()->create(['school_id' => null]);
    $operator->assignRole('system_admin');
    $this->actingAs($operator);

    expect(School::query()->count())->toBe(1);
    expect(User::query()->count())->toBe(0);
    expect(User::find($teacher->id))->toBeNull();

    // Holding another role or having a school does not create an operator bypass.
    $operator->school_id = $teacher->school_id;
    $operator->assignRole('sped_teacher');
    expect(User::query()->count())->toBe(0);
});

test('school policies restrict changes to administrators with the correct scope', function () {
    $this->seed(RolesSeeder::class);
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $teacher = User::factory()->for($school)->create();
    $teacher->assignRole('sped_teacher');

    expect(Gate::forUser($teacher)->allows('view', $school))->toBeTrue();
    expect(Gate::forUser($teacher)->allows('update', $school))->toBeFalse();
    $teacher->assignRole('school_admin');
    expect(Gate::forUser($teacher)->allows('update', $school))->toBeTrue();
    expect(Gate::forUser($teacher)->allows('view', $otherSchool))->toBeFalse();
    expect(Gate::forUser($teacher)->allows('update', $otherSchool))->toBeFalse();
    expect(Gate::forUser($teacher)->allows('create', School::class))->toBeFalse();
    expect(Gate::forUser($teacher)->allows('delete', $school))->toBeFalse();

    $operator = User::factory()->create(['school_id' => null]);
    $operator->assignRole('system_admin');
    expect(Gate::forUser($operator)->allows('create', School::class))->toBeTrue();
    expect(Gate::forUser($operator)->allows('update', $school))->toBeTrue();
});

test('authentication finds active accounts before school context exists', function () {
    $schoolAUser = User::factory()->create();
    $schoolBUser = User::factory()->create();
    expect(User::query()->count())->toBe(0);

    $this->post('/login', ['email' => $schoolBUser->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($schoolBUser);
    expect(User::find($schoolAUser->id))->toBeNull();
    expect(User::query()->pluck('id')->all())->toBe([$schoolBUser->id]);
});

test('inactive accounts cannot login or restore remembered sessions', function () {
    $inactive = User::factory()->create(['is_active' => false]);
    $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $provider = Auth::guard()->getProvider();
    expect($provider->retrieveById($inactive->id))->toBeNull();
    expect($provider->retrieveByToken($inactive->id, $inactive->remember_token))->toBeNull();
    $this->actingAs($inactive);
    expect(User::query()->count())->toBe(0);
    expect(School::query()->count())->toBe(0);
    expect(Gate::forUser($inactive)->allows('viewAny', School::class))->toBeFalse();
});

test('authentication provider restores only matching active remember tokens', function () {
    $active = User::factory()->create();
    $provider = Auth::guard()->getProvider();

    expect($provider->retrieveById($active->id)->id)->toBe($active->id);
    expect($provider->retrieveByToken($active->id, $active->remember_token)->id)->toBe($active->id);
    expect($provider->retrieveByToken($active->id, 'incorrect-token'))->toBeNull();
});

test('untrusted account attributes cannot change school membership or active status', function () {
    $user = User::factory()->create();
    $originalSchool = $user->school_id;
    $otherSchool = School::factory()->create();
    $this->actingAs($user);

    $user->fill(['school_id' => $otherSchool->id, 'is_active' => false]);
    expect($user->school_id)->toBe($originalSchool);
    expect($user->is_active)->toBeTrue();
});
