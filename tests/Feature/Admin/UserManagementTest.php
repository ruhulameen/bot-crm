<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

test('only admins can manage users', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
});

test('users can be searched and filtered', function () {
    User::factory()->create(['name' => 'Zed Findable']);
    User::factory()->role(UserRole::Admin)->create(['name' => 'Mia Admin']);
    User::factory()->status(UserStatus::Suspended)->create(['name' => 'Sam Suspended']);

    $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'findable']))
        ->assertSee('Zed Findable')->assertDontSee('Mia Admin');
    $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'admin']))
        ->assertSee('Mia Admin')->assertDontSee('Zed Findable');
    $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'suspended']))
        ->assertSee('Sam Suspended')->assertDontSee('Mia Admin');
});

test('admin can create a user', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'New Person', 'email' => 'new@example.com', 'password' => 'secret-pass', 'role' => 'user', 'status' => 'active',
    ])->assertRedirect(route('admin.users.index'));

    $user = User::firstWhere('email', 'new@example.com');
    expect($user->role)->toBe(UserRole::User)->and($user->status)->toBe(UserStatus::Active);
});

test('creating a user validates input', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), ['name' => '', 'email' => $this->admin->email, 'role' => 'root'])
        ->assertSessionHasErrors(['name', 'email', 'role', 'status', 'password']);
});

test('admin can update a user and keeps the password when left blank', function () {
    $user = User::factory()->create();
    $hash = $user->password;

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'Renamed', 'email' => $user->email, 'role' => 'user', 'status' => 'suspended', 'password' => '',
    ])->assertRedirect(route('admin.users.index'));

    $user->refresh();
    expect($user->name)->toBe('Renamed')->and($user->status)->toBe(UserStatus::Suspended)->and($user->password)->toBe($hash);
});

test('admin cannot change own role or status', function () {
    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
        'name' => 'Me', 'email' => $this->admin->email, 'role' => 'user', 'status' => 'suspended',
    ])->assertRedirect();

    $this->admin->refresh();
    expect($this->admin->role)->toBe(UserRole::Admin)->and($this->admin->status)->toBe(UserStatus::Active);
});

test('admin can delete another user but not themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
    $this->assertModelMissing($user);

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertForbidden();
    $this->assertModelExists($this->admin);
});
