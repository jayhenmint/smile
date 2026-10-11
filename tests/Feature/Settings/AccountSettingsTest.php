<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesSeeder::class);
});

function teacherAccountInput(array $overrides = []): array
{
    return array_merge([
        'name' => 'Example Teacher', 'email' => 'new-teacher@example.test',
        'password' => 'Example-Password-123', 'password_confirmation' => 'Example-Password-123',
        'school_admin' => false,
    ], $overrides);
}

test('account list and pagination contain only the administrator school and omit secrets', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['sped_teacher', 'school_admin']);
    User::factory()->count(16)->create(['school_id' => $admin->school_id]);
    $other = User::factory()->create();
    $this->actingAs($admin)->get(route('accounts.index', ['school_id' => $other->school_id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/accounts')->where('accounts.total', 17)
        ->has('accounts.data', 15)->where('accountSettingsNav.visible', true)
        ->missing('accounts.data.0.password')->missing('accounts.data.0.remember_token')
        ->where('accounts.data', fn ($accounts) => collect($accounts)->every(fn ($user) => $user['id'] !== $other->id)));
    $this->get(route('accounts.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('accounts.data', 2)->where('accounts.total', 17));
});

test('administrator creates an active teacher who can login with the supplied password', function (bool $alsoAdmin) {
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    $this->actingAs($admin)->post(route('accounts.store'), teacherAccountInput(['school_admin' => $alsoAdmin]))
        ->assertSessionHasNoErrors()->assertRedirect(route('accounts.index'));
    $created = User::where('email', 'new-teacher@example.test')->sole();
    expect($created->school_id)->toBe($admin->school_id);
    expect($created->is_active)->toBeTrue();
    expect($created->email_verified_at)->toBeNull();
    expect(Hash::check('Example-Password-123', $created->password))->toBeTrue();
    expect($created->getRoleNames()->sort()->values()->all())
        ->toBe($alsoAdmin ? ['school_admin', 'sped_teacher'] : ['sped_teacher']);
    $this->post(route('logout'));
    $this->post(route('login'), ['email' => $created->email, 'password' => 'Example-Password-123'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($created);
    $this->get(route('dashboard'))->assertOk();
})->with([false, true]);

test('guests cannot list or create school accounts', function () {
    $this->get(route('accounts.index'))->assertRedirect(route('login'));
    $this->post(route('accounts.store'), teacherAccountInput())->assertRedirect(route('login'));
    $this->assertDatabaseCount('users', 0);
});

test('users without school administration access cannot list or create accounts', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);
    if ($role === 'system_admin') {
        $user->assignRole('school_admin');
    }
    $this->actingAs($user)->get(route('accounts.index'))->assertForbidden();
    $this->post(route('accounts.store'), teacherAccountInput())->assertForbidden();
    $this->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('accountSettingsNav.visible', false));
    $this->assertDatabaseCount('users', 1);
})->with(['sped_teacher', 'regular_teacher', 'parent', 'system_admin']);

test('inactive and unassigned administrators cannot create or list accounts', function () {
    foreach ([['is_active' => false], ['school_id' => null]] as $attributes) {
        $admin = User::factory()->create($attributes);
        $admin->assignRole('school_admin');
        $this->actingAs($admin)->get(route('accounts.index'))->assertForbidden();
        $this->post(route('accounts.store'), teacherAccountInput())->assertForbidden();
    }
    $this->assertDatabaseMissing('users', ['email' => 'new-teacher@example.test']);
});

test('administrator cannot inject school membership roles permissions or verification', function (array $overrides, string $error) {
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    $other = User::factory()->create();
    if ($error === 'school_id') {
        $overrides['school_id'] = $other->school_id;
    }
    $this->actingAs($admin)->post(route('accounts.store'), teacherAccountInput($overrides))
        ->assertSessionHasErrors($error);
    $this->assertDatabaseCount('users', 2);
})->with([
    'another school' => [['school_id' => 1], 'school_id'],
    'system role' => [['roles' => ['system_admin']], 'roles'],
    'future parent role' => [['roles' => ['parent']], 'roles'],
    'permissions' => [['permissions' => ['all']], 'permissions'],
    'activation' => [['is_active' => false], 'is_active'],
    'verification' => [['email_verified_at' => '2026-10-11'], 'email_verified_at'],
    'invalid admin flag' => [['school_admin' => 'system_admin'], 'school_admin'],
]);

test('invalid credentials and duplicate emails across schools cannot create accounts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('school_admin');
    $other = User::factory()->create();
    $this->actingAs($admin);
    $this->post(route('accounts.store'), teacherAccountInput(['email' => $other->email]))
        ->assertSessionHasErrors('email');
    $this->post(route('accounts.store'), teacherAccountInput([
        'name' => '', 'email' => 'invalid', 'password' => 'short', 'password_confirmation' => 'different',
    ]))->assertSessionHasErrors(['name', 'email', 'password']);
    $this->post(route('accounts.store'), teacherAccountInput(['email' => 'UPPER@example.test']))
        ->assertSessionHasErrors('email');
    $this->post(route('accounts.store'), teacherAccountInput(['password_confirmation' => 'different']))
        ->assertSessionHasErrors('password');
    $this->assertDatabaseCount('users', 2);
});
