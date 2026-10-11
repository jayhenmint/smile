<?php

use App\Auth\SchoolUserProvider;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesSeeder::class);
});

function schoolAccountAdministrator(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(['sped_teacher', 'school_admin']);

    return $admin;
}

test('administrator changes another teacher access while retaining the teacher role', function () {
    $admin = schoolAccountAdministrator();
    $teacher = User::factory()->create(['school_id' => $admin->school_id]);
    $teacher->assignRole('sped_teacher');
    $original = $teacher->only(['school_id', 'name', 'email', 'password', 'email_verified_at']);
    $this->actingAs($admin)->patch(route('accounts.update', $teacher), [
        'school_admin' => true, 'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('accounts.index'));
    expect($teacher->fresh()->getRoleNames()->sort()->values()->all())->toBe(['school_admin', 'sped_teacher']);
    $this->patch(route('accounts.update', $teacher), [
        'school_admin' => false, 'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('accounts.index'));
    expect($teacher->fresh()->getRoleNames()->all())->toBe(['sped_teacher']);
    expect($teacher->fresh()->only(array_keys($original)))->toEqual($original);
});

test('deactivation revokes sessions and remembered login and reactivation requires login', function () {
    $admin = schoolAccountAdministrator();
    $teacher = User::factory()->create(['school_id' => $admin->school_id, 'remember_token' => 'old-remember-token']);
    $teacher->assignRole('sped_teacher');
    foreach ([$admin, $teacher] as $user) {
        DB::table('sessions')->insert([
            'id' => 'session-'.$user->id, 'user_id' => $user->id,
            'payload' => base64_encode(serialize([])), 'last_activity' => time(),
        ]);
    }
    $provider = new SchoolUserProvider(app('hash'), User::class);
    $this->actingAs($admin)->patch(route('accounts.update', $teacher), [
        'school_admin' => false, 'is_active' => false,
    ])->assertSessionHasNoErrors()->assertRedirect(route('accounts.index'));
    expect($teacher->fresh()->is_active)->toBeFalse();
    expect($teacher->fresh()->remember_token)->toBeNull();
    expect($provider->retrieveById($teacher->id))->toBeNull();
    expect($provider->retrieveByToken($teacher->id, 'old-remember-token'))->toBeNull();
    $this->assertDatabaseMissing('sessions', ['user_id' => $teacher->id]);
    $this->assertDatabaseHas('sessions', ['user_id' => $admin->id]);
    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $teacher->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->actingAs($admin)->patch(route('accounts.update', $teacher), [
        'school_admin' => false, 'is_active' => true,
    ])->assertSessionHasNoErrors();
    expect($provider->retrieveByToken($teacher->id, 'old-remember-token'))->toBeNull();
    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $teacher->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($teacher);
});

test('administrator cannot deactivate or demote their own account', function () {
    $admin = schoolAccountAdministrator();
    $this->actingAs($admin)->patch(route('accounts.update', $admin), [
        'school_admin' => false, 'is_active' => false,
    ])->assertForbidden();
    expect($admin->fresh()->is_active)->toBeTrue();
    expect($admin->fresh()->hasRole('school_admin'))->toBeTrue();
    $this->get(route('accounts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('accounts.data.0.can_update', false));
});

test('cross school account access changes are not found and leave the target unchanged', function () {
    $admin = schoolAccountAdministrator();
    $other = User::factory()->create();
    $other->assignRole(['sped_teacher', 'school_admin']);
    $this->actingAs($admin)->patch(route('accounts.update', $other), [
        'school_admin' => false, 'is_active' => false,
    ])->assertNotFound();
    $this->assertDatabaseHas('users', ['id' => $other->id, 'is_active' => true]);
    expect($other->hasRole('school_admin'))->toBeTrue();
});

test('special role accounts cannot be changed through teacher management', function (string $role) {
    $admin = schoolAccountAdministrator();
    $target = User::factory()->create(['school_id' => $admin->school_id]);
    $target->assignRole($role);
    $target->assignRole('sped_teacher');
    $this->actingAs($admin)->patch(route('accounts.update', $target), [
        'school_admin' => true, 'is_active' => false,
    ])->assertForbidden();
    expect($target->fresh()->is_active)->toBeTrue();
    expect($target->fresh()->hasRole('school_admin'))->toBeFalse();
})->with(['system_admin', 'parent', 'regular_teacher']);

test('accounts without the teacher role cannot be changed', function () {
    $admin = schoolAccountAdministrator();
    $target = User::factory()->create(['school_id' => $admin->school_id]);
    $this->actingAs($admin)->patch(route('accounts.update', $target), [
        'school_admin' => true, 'is_active' => false,
    ])->assertForbidden();
    expect($target->fresh()->is_active)->toBeTrue();
    expect($target->fresh()->getRoleNames())->toBeEmpty();
});

test('a teacher cannot grant themselves school administrator access', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('sped_teacher');
    $this->actingAs($teacher)->patch(route('accounts.update', $teacher), [
        'school_admin' => true, 'is_active' => true,
    ])->assertForbidden();
    expect($teacher->fresh()->hasRole('school_admin'))->toBeFalse();
});

test('inactive and nonadministrative actors cannot change teacher access', function (string $role, bool $active) {
    $actor = User::factory()->create(['is_active' => $active]);
    $actor->assignRole($role);
    if ($role === 'system_admin') {
        $actor->assignRole('school_admin');
    }
    $target = User::factory()->create(['school_id' => $actor->school_id]);
    $target->assignRole('sped_teacher');
    $response = $this->actingAs($actor)->patch(route('accounts.update', $target), [
        'school_admin' => true, 'is_active' => false,
    ]);
    // The global scope conceals targets before policy checks for inactive/system accounts.
    $active && $role !== 'system_admin' ? $response->assertForbidden() : $response->assertNotFound();
    $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    expect($target->hasRole('school_admin'))->toBeFalse();
})->with([
    ['sped_teacher', true], ['parent', true], ['regular_teacher', true],
    ['system_admin', true], ['school_admin', false],
]);

test('guests cannot change teacher access', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('sped_teacher');
    $this->patch(route('accounts.update', $teacher), [
        'school_admin' => true, 'is_active' => false,
    ])->assertRedirect(route('login'));
    $this->assertDatabaseHas('users', ['id' => $teacher->id, 'is_active' => true]);
});

test('invalid flags and unrelated attributes cannot change access', function (array $extra, string $error) {
    $admin = schoolAccountAdministrator();
    $teacher = User::factory()->create(['school_id' => $admin->school_id]);
    $teacher->assignRole('sped_teacher');
    $this->actingAs($admin)->patch(route('accounts.update', $teacher), array_merge([
        'school_admin' => false, 'is_active' => true,
    ], $extra))->assertSessionHasErrors($error);
    expect($teacher->fresh()->is_active)->toBeTrue();
    expect($teacher->fresh()->getRoleNames()->all())->toBe(['sped_teacher']);
})->with([
    [['school_admin' => 'system_admin'], 'school_admin'],
    [['is_active' => 'yes'], 'is_active'],
    [['school_admin' => null], 'school_admin'],
    [['is_active' => null], 'is_active'],
    [['school_id' => 999], 'school_id'],
    [['roles' => ['system_admin']], 'roles'],
    [['permissions' => ['all']], 'permissions'],
    [['email_verified_at' => '2026-10-11'], 'email_verified_at'],
    [['name' => 'Changed Name'], 'name'],
    [['email' => 'changed@example.test'], 'email'],
    [['password' => 'Changed-Password-123'], 'password'],
    [['remember_token' => 'injected-token'], 'remember_token'],
]);
