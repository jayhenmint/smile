<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

test('seeding defines the required roles without creating default accounts', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->orderBy('name')->pluck('name')->all())->toBe([
        'parent', 'regular_teacher', 'school_admin', 'sped_teacher', 'system_admin',
    ]);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('permissions', 0);
});

test('a teacher can hold both roles and be retrieved through each role', function () {
    $this->seed(DatabaseSeeder::class);
    $teacher = User::factory()->create();
    $teacher->assignRole(['sped_teacher', 'school_admin']);

    expect(Str::isUuid($teacher->id))->toBeTrue();
    expect($teacher->fresh()->hasAllRoles(['sped_teacher', 'school_admin']))->toBeTrue();
    expect(Role::findByName('sped_teacher')->users->sole()->id)->toBe($teacher->id);
    expect(Role::findByName('school_admin')->users->sole()->id)->toBe($teacher->id);

    $teacher->removeRole('school_admin');
    expect($teacher->fresh()->hasRole('sped_teacher'))->toBeTrue();
    expect($teacher->fresh()->hasRole('school_admin'))->toBeFalse();
});

test('roles are not granted to new users automatically', function () {
    $this->seed(DatabaseSeeder::class);
    expect(User::factory()->create()->roles)->toBeEmpty();
});

test('system admin has no blanket permission bypass', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    expect($admin->can('view learner records'))->toBeFalse();
});
