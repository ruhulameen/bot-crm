<?php

use App\Models\License;
use App\Models\Product;
use Database\Factories\LicenseFactory;

function credentials(License $license, array $overrides = []): array
{
    return ['product' => $license->product->slug, 'username' => $license->username, 'password' => LicenseFactory::PASSWORD, ...$overrides];
}

test('a bot can sign in and the first sign-in is recorded', function () {
    $license = License::factory()->create();

    $this->postJson(route('api.v1.licenses.login'), credentials($license))
        ->assertOk()
        ->assertJson(['valid' => true, 'product' => $license->product->slug, 'status' => 'active'])
        ->assertJsonMissingPath('data');

    expect($license->refresh()->hasBeenSeen())->toBeTrue()->and($license->isOnline())->toBeTrue();
});

test('usernames are case-insensitive and the same username can exist for different products', function () {
    $first = License::factory()->create(['username' => 'shared-name']);
    $second = License::factory()->create(['username' => 'shared-name']);

    $this->postJson(route('api.v1.licenses.login'), credentials($first, ['username' => 'SHARED-Name']))->assertOk();
    $this->postJson(route('api.v1.licenses.login'), credentials($second))->assertOk();
});

test('several machines can use the same account, there is no device binding', function () {
    $license = License::factory()->create();

    $this->postJson(route('api.v1.licenses.login'), credentials($license))->assertOk();
    $this->postJson(route('api.v1.licenses.login'), credentials($license))->assertOk();
    $this->postJson(route('api.v1.licenses.heartbeat'), credentials($license))->assertOk();
});

test('wrong credentials, wrong product and inactive product all look the same', function (string $case) {
    $license = License::factory()->for(Product::factory()->state(['is_active' => $case !== 'inactive']))->create();

    $data = match ($case) {
        'wrong-password' => credentials($license, ['password' => 'nope-nope']),
        'unknown-user' => credentials($license, ['username' => 'nobody']),
        'wrong-product' => credentials($license, ['product' => 'something-else']),
        default => credentials($license),
    };

    $this->postJson(route('api.v1.licenses.login'), $data)->assertUnauthorized()->assertExactJson(['valid' => false, 'reason' => 'invalid_credentials']);
    expect($license->refresh()->hasBeenSeen())->toBeFalse();
})->with(['wrong-password', 'unknown-user', 'wrong-product', 'inactive']);

test('revoked and expired accounts are rejected after the credentials are verified', function (string $state, string $reason) {
    $license = License::factory()->{$state}()->create();

    $this->postJson(route('api.v1.licenses.login'), credentials($license))->assertForbidden()->assertJson(['valid' => false, 'reason' => $reason]);
    $this->postJson(route('api.v1.licenses.login'), credentials($license, ['password' => 'wrong-pass']))->assertUnauthorized();
})->with([['revoked', 'revoked'], ['expired', 'expired']]);

test('the payload is validated', function () {
    $this->postJson(route('api.v1.licenses.login'), [])->assertUnprocessable()->assertJsonValidationErrors(['product', 'username', 'password']);
});

test('heartbeat refreshes presence and clears the offline flag', function () {
    $license = License::factory()->offline()->create(['offline_notified_at' => now()]);

    $this->postJson(route('api.v1.licenses.heartbeat'), credentials($license))->assertOk()->assertJson(['valid' => true]);

    expect($license->refresh()->isOnline())->toBeTrue()->and($license->offline_notified_at)->toBeNull();
});

test('heartbeat tells a bot when its account was revoked or expired', function () {
    $revoked = License::factory()->online()->create();
    $revoked->revoke();
    $this->postJson(route('api.v1.licenses.heartbeat'), credentials($revoked))->assertForbidden()->assertJson(['reason' => 'revoked']);

    $expired = License::factory()->online()->expired()->create();
    $this->postJson(route('api.v1.licenses.heartbeat'), credentials($expired))->assertForbidden()->assertJson(['reason' => 'expired']);
});

test('an extended license works again for a bot that was told it expired', function () {
    $license = License::factory()->expired()->create();
    $this->postJson(route('api.v1.licenses.heartbeat'), credentials($license))->assertForbidden();

    $license->extendByDays(30);

    $this->postJson(route('api.v1.licenses.heartbeat'), credentials($license))->assertOk();
});

test('repeated failures are blocked, and a correct password clears the counter', function () {
    $license = License::factory()->create();

    foreach (range(1, 9) as $ignored) {
        $this->postJson(route('api.v1.licenses.login'), credentials($license, ['password' => 'wrong-pass']))->assertUnauthorized();
    }
    $this->postJson(route('api.v1.licenses.login'), credentials($license))->assertOk();

    foreach (range(1, 10) as $ignored) {
        $this->postJson(route('api.v1.licenses.login'), credentials($license, ['password' => 'wrong-pass']))->assertUnauthorized();
    }
    $this->postJson(route('api.v1.licenses.login'), credentials($license))->assertStatus(429)->assertJson(['reason' => 'too_many_attempts']);
});

test('the password is never exposed', function () {
    $license = License::factory()->create();

    $this->postJson(route('api.v1.licenses.login'), credentials($license))->assertJsonMissing(['password' => LicenseFactory::PASSWORD]);
    expect($license->refresh()->toArray())->not->toHaveKey('password')
        ->and($license->getRawOriginal('password'))->not->toBe(LicenseFactory::PASSWORD);
});
