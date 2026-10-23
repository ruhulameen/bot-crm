<?php

use App\Enums\UserStatus;
use App\Models\User;

test('login screen renders', function () {
    $this->get(route('login'))->assertOk();
});

test('active users can log in and last login is recorded', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

test('users cannot log in with a wrong password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('non-active users cannot log in', function (UserStatus $status) {
    $user = User::factory()->status($status)->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([UserStatus::Inactive, UserStatus::Suspended]);

test('login is rate limited after repeated failures', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('users can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a user suspended mid-session is logged out', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $user->update(['status' => UserStatus::Suspended]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
