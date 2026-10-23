<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guests are sent to login', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->put(route('profile.update'), [])->assertRedirect(route('login'));
    $this->put(route('profile.password.update'), [])->assertRedirect(route('login'));
});

test('admins and users both see their own profile in their own layout', function () {
    $admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Ada Admin', 'email' => 'ada@example.com']);
    $user = User::factory()->create(['name' => 'Una User', 'email' => 'una@example.com']);

    $this->actingAs($admin)->get(route('profile.edit'))->assertOk()
        ->assertSee('Ada Admin')->assertSee('ada@example.com')->assertSee('Change password')->assertSee('Core')->assertDontSee('una@example.com');

    $this->actingAs($user)->get(route('profile.edit'))->assertOk()
        ->assertSee('Una User')->assertSee('una@example.com')->assertSee('Menu')->assertDontSee('Core');
});

test('the menu links to the profile', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertSee(route('profile.edit'), false);
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get(route('admin.licenses.index'))->assertSee(route('profile.edit'), false);
});

test('name and email can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.update'), ['name' => 'New Name', 'email' => 'new@example.com'])
        ->assertRedirect(route('profile.edit'))->assertSessionHas('status');

    expect($user->refresh()->name)->toBe('New Name')->and($user->email)->toBe('new@example.com');
});

test('keeping the same email is allowed but another users email is not', function () {
    $user = User::factory()->create(['email' => 'mine@example.com']);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($user)->put(route('profile.update'), ['name' => 'Me', 'email' => 'mine@example.com'])->assertSessionHasNoErrors();
    $this->actingAs($user)->put(route('profile.update'), ['name' => 'Me', 'email' => 'taken@example.com'])->assertSessionHasErrors('email');
    $this->actingAs($user)->put(route('profile.update'), ['name' => '', 'email' => 'not-an-email'])->assertSessionHasErrors(['name', 'email']);
});

test('role and status cannot be changed through the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Sneaky', 'email' => $user->email, 'role' => 'admin', 'status' => 'suspended',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->role)->toBe(UserRole::User)->and($user->status)->toBe(UserStatus::Active);
});

test('the password can be changed with the current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.password.update'), [
        'current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
    ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors()->assertSessionHas('status');

    expect(Hash::check('brand-new-pass', $user->refresh()->password))->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeFalse();
});

test('the user stays signed in on this browser after changing the password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.password.update'), [
        'current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
    ]);

    $this->get(route('profile.edit'))->assertOk();
    $this->assertAuthenticatedAs($user->refresh());
});

test('the new password works for logging in and the old one does not', function () {
    $user = User::factory()->create(['email' => 'login@example.com']);

    $this->actingAs($user)->put(route('profile.password.update'), [
        'current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
    ]);
    auth()->logout();

    $this->post('/login', ['email' => 'login@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => 'login@example.com', 'password' => 'brand-new-pass'])->assertRedirect(route('dashboard'));
});

test('password changes are validated', function (array $payload, array|string $errors) {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.password.update'), $payload)->assertSessionHasErrors($errors);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->with([
    'wrong current password' => [['current_password' => 'nope', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'], 'current_password'],
    'missing current password' => [['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'], 'current_password'],
    'confirmation mismatch' => [['current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'different'], 'password'],
    'too short' => [['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'same as current' => [['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'], 'password'],
]);

test('suspended users cannot reach the profile', function () {
    $user = User::factory()->status(UserStatus::Suspended)->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('the password endpoint is rate limited', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $ignored) {
        $this->actingAs($user)->put(route('profile.password.update'), ['current_password' => 'wrong', 'password' => 'x']);
    }

    $this->actingAs($user)->put(route('profile.password.update'), ['current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
        ->assertStatus(429);
});

test('a session that was started with the old password is signed out once the password changed', function () {
    $user = User::factory()->create();
    $oldSessionHash = $user->password;

    $this->actingAs($user)->put(route('profile.password.update'), [
        'current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
    ]);

    // Another browser still carries the hash of the old password in its session.
    $this->flushSession();
    $this->actingAs($user->refresh())->withSession(['password_hash_web' => $oldSessionHash])
        ->get(route('profile.edit'))->assertRedirect(route('login'));
});
