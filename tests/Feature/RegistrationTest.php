<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

test('public registration is closed', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertNotFound();

    expect(User::count())->toBe(0);
});

test('users can be created from the terminal', function () {
    $this->artisan('user:create', ['--name' => 'Boss', '--email' => 'boss@example.com', '--password' => 'secret-pass', '--role' => 'admin'])
        ->assertSuccessful();

    $user = User::firstWhere('email', 'boss@example.com');

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->status)->toBe(UserStatus::Active);
    $this->post('/login', ['email' => 'boss@example.com', 'password' => 'secret-pass'])->assertRedirect(route('dashboard'));
});

test('terminal user creation rejects invalid input', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('user:create', ['--name' => 'X', '--email' => 'taken@example.com', '--password' => 'secret-pass', '--role' => 'admin'])
        ->assertFailed();
    $this->artisan('user:create', ['--name' => 'X', '--email' => 'new@example.com', '--password' => 'secret-pass', '--role' => 'root'])
        ->assertFailed();
});
