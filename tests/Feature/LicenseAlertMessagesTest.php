<?php

use App\Jobs\SendTelegramMessage;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use App\Services\LicenseAlertMessages;
use Database\Factories\LicenseFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.telegram.token' => 'token', 'services.telegram.chat_id' => '123']);
});

function seenLicense(array $attributes = []): License
{
    return License::factory()
        ->for(Product::factory()->state(['name' => 'Oreo Bot', 'slug' => 'oreo-bot']))
        ->for(User::factory()->state(['name' => 'Jane Customer', 'email' => 'jane@example.com']))
        ->offline(15)
        ->create(['username' => 'jane-bot', ...$attributes]);
}

function assertIdentifiable(string $message): void
{
    expect($message)->toContain('Oreo Bot')
        ->toContain('Username: jane-bot')
        ->toContain('Customer: Jane Customer (jane@example.com)')
        ->toContain('Expires:');
}

test('every alert identifies the product, username and customer', function () {
    $license = seenLicense()->load(['product', 'user']);

    assertIdentifiable(LicenseAlertMessages::firstLogin($license));
    assertIdentifiable(LicenseAlertMessages::backOnline($license, now()->subMinutes(12)));
    assertIdentifiable(LicenseAlertMessages::offline(collect([$license])));
    assertIdentifiable(LicenseAlertMessages::expiring(collect([$license])));
});

test('alerts never contain device details or the password', function () {
    $license = seenLicense()->load(['product', 'user']);

    foreach ([LicenseAlertMessages::firstLogin($license), LicenseAlertMessages::offline(collect([$license]))] as $message) {
        expect($message)->not->toContain('Device')->not->toContain('IP:')->not->toContain(LicenseFactory::PASSWORD);
    }
});

test('offline alerts show when the account was last seen and back-online alerts show the downtime', function () {
    $license = seenLicense()->load(['product', 'user']);

    expect(LicenseAlertMessages::offline(collect([$license])))->toContain('Last seen:')->toContain('15 minutes ago')
        ->and(LicenseAlertMessages::backOnline($license, now()->subMinutes(12)))->toContain('Was offline for: 12 minutes');
});

test('licenses without a customer or sign-in are still described', function () {
    $license = License::factory()->create(['user_id' => null])->load(['product', 'user']);

    expect(LicenseAlertMessages::expiring(collect([$license])))->toContain('Customer: none')->toContain('Last seen: never');
});

test('the first-login and back-online notifications sent by the api carry the details', function () {
    Bus::fake();
    $license = License::factory()
        ->for(Product::factory()->state(['slug' => 'oreo-bot', 'name' => 'Oreo Bot']))
        ->for(User::factory()->state(['name' => 'Jane Customer', 'email' => 'jane@example.com']))
        ->create(['username' => 'jane-bot']);
    $credentials = ['product' => 'oreo-bot', 'username' => 'jane-bot', 'password' => LicenseFactory::PASSWORD];

    $this->postJson(route('api.v1.licenses.login'), $credentials)->assertOk();
    Bus::assertDispatchedAfterResponse(SendTelegramMessage::class, function ($job) {
        assertIdentifiable($job->message);

        return str_contains($job->message, 'First login');
    });

    Bus::fake();
    $license->refresh()->update(['last_seen_at' => now()->subMinutes(20), 'offline_notified_at' => now()]);
    $this->postJson(route('api.v1.licenses.heartbeat'), $credentials)->assertOk();
    Bus::assertDispatchedAfterResponse(SendTelegramMessage::class, function ($job) {
        assertIdentifiable($job->message);

        return str_contains($job->message, 'Back online') && str_contains($job->message, 'Was offline for: 20 minutes');
    });
});

test('many offline accounts are split over several messages that each carry full details', function () {
    Http::fake();
    License::factory()->count(20)->offline()->create();

    $this->artisan('licenses:check-offline')->assertSuccessful();

    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => strlen($request['text']) < 4096 && str_contains($request['text'], 'Customer:') && str_contains($request['text'], 'Username:'));
    expect(License::whereNull('offline_notified_at')->count())->toBe(0);
});

test('only the messages Telegram accepted are marked as reported', function () {
    Http::fake(['api.telegram.org/*' => Http::sequence()->push(['ok' => true])->push(['ok' => false], 500)->push(['ok' => true])]);
    License::factory()->count(20)->offline()->create();

    $this->artisan('licenses:check-offline')->assertSuccessful();

    // Messages of 8, 8 and 4 accounts: the failed middle one stays pending for the next run.
    expect(License::whereNotNull('offline_notified_at')->count())->toBe(12)
        ->and(License::whereNull('offline_notified_at')->count())->toBe(8);
});
