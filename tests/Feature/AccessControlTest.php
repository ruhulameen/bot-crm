<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'active', 'role:admin'])
        ->get('/_test/staff', fn () => 'ok');
});

test('guests are redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users are redirected away from guest pages', function () {
    $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('dashboard'));
});

test('role middleware allows listed roles and rejects others', function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get('/_test/staff')->assertOk();
    $this->actingAs(User::factory()->role(UserRole::User)->create())->get('/_test/staff')->assertForbidden();
});

test('dashboard shows role and status', function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Admin')
        ->assertSee('Active');
});

test('dashboard uses the admin menu layout for admins and the user layout for users', function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get(route('dashboard'))->assertSee('Core');
    $this->actingAs(User::factory()->role(UserRole::User)->create())->get(route('dashboard'))->assertSee('Menu')->assertDontSee('Core');
});
