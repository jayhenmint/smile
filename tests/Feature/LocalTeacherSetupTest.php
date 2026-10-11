<?php

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('local setup creates a teacher who can login and open the dashboard', function () {
    app()->instance('env', 'local');
    $exit = Artisan::call('smile:create-local-teacher', [
        '--school' => 'Example Development School',
        '--name' => 'Example Teacher',
        '--email' => 'teacher@example.test',
    ]);
    expect($exit)->toBe(0);
    preg_match('/Password: ([a-f0-9]{32})/', Artisan::output(), $matches);
    $password = $matches[1];
    $teacher = User::withoutGlobalScope(SchoolScope::class)->sole();
    expect($teacher->hasAllRoles(['school_admin', 'sped_teacher']))->toBeTrue();
    expect($teacher->hasRole('system_admin'))->toBeFalse();
    expect(Hash::check($password, $teacher->password))->toBeTrue();
    expect($teacher->email_verified_at)->toBeNull();

    app()->instance('env', 'testing');
    $this->post('/login', ['email' => $teacher->email, 'password' => $password])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($teacher);
    expect($teacher->school->name)->toBe('Example Development School');
    $this->get('/dashboard')->assertOk();

    app()->instance('env', 'local');
    $this->artisan('smile:create-local-teacher', [
        '--school' => 'Another School', '--name' => 'Another Teacher', '--email' => 'other@example.test',
    ])->assertFailed();
    expect(User::withoutGlobalScope(SchoolScope::class)->count())->toBe(1);
    expect(School::withoutGlobalScope(SchoolScope::class)->count())->toBe(1);
    expect(Hash::check($password, $teacher->fresh()->password))->toBeTrue();
});

test('initial setup is unavailable outside local development', function () {
    app()->instance('env', 'production');
    $this->artisan('smile:create-local-teacher', [
        '--school' => 'Example School', '--name' => 'Example Teacher', '--email' => 'teacher@example.test',
    ])->assertFailed();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('schools', 0);
});

test('invalid local setup inputs leave the database unchanged', function () {
    app()->instance('env', 'local');
    $this->artisan('smile:create-local-teacher', [
        '--school' => '', '--name' => '', '--email' => 'invalid',
    ])->assertFailed();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('schools', 0);
});
