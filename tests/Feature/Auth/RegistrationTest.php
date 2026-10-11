<?php

use App\Models\User;

test('the registration screen is unavailable', function (bool $authenticated) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }

    $this->get('/register')->assertNotFound();
})->with([false, true]);

test('registration cannot create an account', function (bool $authenticated) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertDatabaseCount('users', $authenticated ? 1 : 0);
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);

    if (! $authenticated) {
        $this->assertGuest();
    }
})->with([false, true]);
