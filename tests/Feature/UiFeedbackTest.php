<?php

use App\Enums\UserRole;
use App\Models\License;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

test('flash messages are rendered as toasts', function () {
    $this->actingAs($this->admin)->withSession(['status' => 'User created.', 'error' => 'Something failed.'])
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('$store.toasts.add', false)
        ->assertSee('User created.')
        ->assertSee('Something failed.');
});

test('toasts also work on guest pages', function () {
    $this->withSession(['status' => 'Your password has been reset.'])->get(route('login'))
        ->assertOk()->assertSee('$store.toasts.add', false)->assertSee('Your password has been reset.');
});

test('failed validation raises a generic error toast', function () {
    $this->actingAs($this->admin)->followingRedirects()
        ->from(route('admin.users.create'))
        ->post(route('admin.users.store'), ['name' => ''])
        ->assertSee('Please fix the highlighted fields.');
});

test('every page ships the shared confirm dialog', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('aria-modal="true"', false);
});

test('destructive actions ask for confirmation first', function () {
    $other = User::factory()->create();
    $license = License::factory()->online()->create();

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertSee('$store.confirm.ask', false)->assertSee('Delete user')->assertSee('Delete '.$other->name);

    $this->actingAs($this->admin)->get(route('admin.licenses.show', $license))
        ->assertSee('Revoke license')->assertSee('Delete license')->assertSee('Reset password')->assertSee('Update expiry');

    $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk();
});

test('the legacy browser confirm() is no longer used', function () {
    $license = License::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.licenses.show', $license))->assertDontSee('confirm(', false);
});
