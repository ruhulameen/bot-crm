<?php

use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Database\Factories\LicenseFactory;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

test('users cannot access licenses', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.licenses.index'))->assertForbidden();
});

test('issuing a license stores a hashed password and shows the credentials once', function () {
    $product = Product::factory()->create();
    $customer = User::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.licenses.store'), [
        'product_id' => $product->id, 'user_id' => $customer->id, 'days' => 30, 'username' => 'John.Doe', 'password' => 'my-secret-1',
    ]);

    $license = License::firstOrFail();
    $response->assertRedirect(route('admin.licenses.show', $license))->assertSessionHas('credentials', ['username' => 'john.doe', 'password' => 'my-secret-1']);

    expect($license->username)->toBe('john.doe')
        ->and($license->getRawOriginal('password'))->not->toBe('my-secret-1')
        ->and(Hash::check('my-secret-1', $license->getRawOriginal('password')))->toBeTrue()
        ->and($license->expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($license->status)->toBe(LicenseStatus::Active)
        ->and($license->hasBeenSeen())->toBeFalse();

    $this->actingAs($this->admin)->get(route('admin.licenses.show', $license))->assertSee('my-secret-1');
    $this->actingAs($this->admin)->get(route('admin.licenses.show', $license))->assertDontSee('my-secret-1');
});

test('a blank password is generated', function () {
    $this->actingAs($this->admin)->post(route('admin.licenses.store'), [
        'product_id' => Product::factory()->create()->id, 'days' => 7, 'username' => 'auto-pass',
    ])->assertSessionHas('credentials', fn ($c) => strlen($c['password']) === 12);

    $password = session('credentials.password');
    expect(License::firstOrFail()->passwordMatches($password))->toBeTrue();
});

test('issuing validates input and keeps usernames unique per product', function () {
    $taken = License::factory()->create(['username' => 'taken']);

    $this->actingAs($this->admin)->post(route('admin.licenses.store'), ['days' => 0, 'username' => 'Bad Name!'])
        ->assertSessionHasErrors(['product_id', 'days', 'username']);

    $this->actingAs($this->admin)->post(route('admin.licenses.store'), ['product_id' => $taken->product_id, 'days' => 5, 'username' => 'taken'])
        ->assertSessionHasErrors('username');

    $this->actingAs($this->admin)->post(route('admin.licenses.store'), ['product_id' => Product::factory()->create()->id, 'days' => 5, 'username' => 'taken'])
        ->assertSessionHasNoErrors();
});

test('licenses can be filtered by status, product, search and presence', function () {
    $online = License::factory()->online()->create();
    $soon = License::factory()->expiringSoon()->offline()->create();
    $expired = License::factory()->expired()->create();
    $revoked = License::factory()->revoked()->create();

    $see = fn (array $query) => $this->actingAs($this->admin)->get(route('admin.licenses.index', $query));

    $see(['status' => 'expired'])->assertSee($expired->username)->assertDontSee($online->username)->assertDontSee($revoked->username);
    $see(['status' => 'revoked'])->assertSee($revoked->username)->assertDontSee($expired->username);
    $see(['status' => 'expiring'])->assertSee($soon->username)->assertDontSee($online->username);
    $see(['product' => $online->product_id])->assertSee($online->username)->assertDontSee($soon->username);
    $see(['q' => $revoked->user->email])->assertSee($revoked->username)->assertDontSee($online->username);
    $see(['q' => $soon->username])->assertSee($soon->username)->assertDontSee($online->username);
    $see(['presence' => 'online'])->assertSee($online->username)->assertDontSee($soon->username);
    $see(['presence' => 'offline'])->assertSee($soon->username)->assertDontSee($online->username);
    $see(['presence' => 'never'])->assertSee($expired->username)->assertDontSee($online->username);
});

test('extending by days adds to the current expiry', function () {
    $license = License::factory()->create(['expires_at' => now()->addDays(10)]);

    $this->actingAs($this->admin)->post(route('admin.licenses.extend', $license), ['days' => 30])->assertRedirect();

    expect($license->refresh()->expires_at->isSameDay(now()->addDays(40)))->toBeTrue();
});

test('renewing an expired license by days starts from today', function () {
    $license = License::factory()->expired()->create();

    $this->actingAs($this->admin)->post(route('admin.licenses.extend', $license), ['days' => 15]);

    expect($license->refresh()->expires_at->isSameDay(now()->addDays(15)))->toBeTrue()
        ->and($license->status)->toBe(LicenseStatus::Active);
});

test('a license can be extended to a custom date, valid through the end of that day', function () {
    $license = License::factory()->expired()->create();
    $date = now()->addDays(100)->toDateString();

    $this->actingAs($this->admin)->post(route('admin.licenses.extend', $license), ['expires_at' => $date])->assertRedirect();

    $license->refresh();
    expect($license->expires_at->toDateString())->toBe($date)
        ->and($license->expires_at->format('H:i'))->toBe('23:59')
        ->and($license->status)->toBe(LicenseStatus::Active);
});

test('extension needs days or a future date', function () {
    $license = License::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.licenses.extend', $license), [])->assertSessionHasErrors(['days', 'expires_at']);
    $this->actingAs($this->admin)->post(route('admin.licenses.extend', $license), ['expires_at' => now()->subDay()->toDateString()])
        ->assertSessionHasErrors('expires_at');
});

test('licenses can be revoked and restored', function () {
    $license = License::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.licenses.revoke', $license));
    expect($license->refresh()->status)->toBe(LicenseStatus::Revoked);

    $this->actingAs($this->admin)->post(route('admin.licenses.restore', $license));
    expect($license->refresh()->status)->toBe(LicenseStatus::Active);
});

test('the password can be reset to a chosen or a generated value', function () {
    $license = License::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.licenses.reset-password', $license), ['password' => 'brand-new-1'])
        ->assertSessionHas('credentials', ['username' => $license->username, 'password' => 'brand-new-1']);
    expect($license->refresh()->passwordMatches('brand-new-1'))->toBeTrue()->and($license->passwordMatches(LicenseFactory::PASSWORD))->toBeFalse();

    $this->actingAs($this->admin)->post(route('admin.licenses.reset-password', $license), ['password' => ''])->assertSessionHas('credentials');
    expect($license->refresh()->passwordMatches('brand-new-1'))->toBeFalse();

    $this->actingAs($this->admin)->post(route('admin.licenses.reset-password', $license), ['password' => '123'])->assertSessionHasErrors('password');
});

test('details can be edited without touching product, expiry or password', function () {
    $license = License::factory()->create();
    $expiry = $license->expires_at;

    $this->actingAs($this->admin)->put(route('admin.licenses.update', $license), ['username' => 'Renamed-User', 'notes' => 'VIP'])
        ->assertRedirect(route('admin.licenses.show', $license));

    $license->refresh();
    expect($license->username)->toBe('renamed-user')->and($license->notes)->toBe('VIP')
        ->and($license->expires_at->eq($expiry))->toBeTrue()->and($license->passwordMatches(LicenseFactory::PASSWORD))->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.licenses.update', $license), ['username' => 'x', 'product_id' => Product::factory()->create()->id, 'days' => 5, 'password' => 'sneaky-pass'])
        ->assertSessionHasErrors(['product_id', 'days', 'password']);
});

test('editing keeps the own username valid and rejects another account username of the same product', function () {
    $first = License::factory()->create(['username' => 'first']);
    $second = License::factory()->for($first->product)->create(['username' => 'second']);

    $this->actingAs($this->admin)->put(route('admin.licenses.update', $first), ['username' => 'first'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->put(route('admin.licenses.update', $first), ['username' => 'second'])->assertSessionHasErrors('username');
});

test('the license page shows activity for seen and unseen accounts', function () {
    $online = License::factory()->online()->create();
    $offline = License::factory()->offline()->create();
    $unseen = License::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.licenses.show', $online))->assertOk()->assertSee('Online');
    $this->actingAs($this->admin)->get(route('admin.licenses.show', $offline))->assertOk()->assertSee('Offline');
    $this->actingAs($this->admin)->get(route('admin.licenses.show', $unseen))->assertOk()->assertSee('has not signed in yet');
});

test('licenses can be deleted', function () {
    $license = License::factory()->online()->create();

    $this->actingAs($this->admin)->delete(route('admin.licenses.destroy', $license))->assertRedirect(route('admin.licenses.index'));

    $this->assertModelMissing($license);
});
