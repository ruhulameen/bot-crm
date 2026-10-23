<?php

use App\Models\License;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.telegram.token' => 'token', 'services.telegram.chat_id' => '123']);
});

test('licenses expiring soon are reported once', function () {
    Http::fake();
    $license = License::factory()->expiringSoon(2)->create();

    $this->artisan('licenses:check-expiring')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request['text'], $license->username) && str_contains($request['text'], 'expiring'));
    expect($license->refresh()->expiry_notified_at)->not->toBeNull();

    $this->artisan('licenses:check-expiring')->assertSuccessful();
    Http::assertSentCount(1);
});

test('extending a license re-arms its expiry alert', function () {
    $license = License::factory()->expiringSoon(2)->create(['expiry_notified_at' => now()]);

    $license->extendByDays(1);
    expect($license->refresh()->expiry_notified_at)->toBeNull();

    $license->update(['expiry_notified_at' => now()]);
    $license->extendUntil(now()->addDays(3));
    expect($license->refresh()->expiry_notified_at)->toBeNull();
});

test('far-off, expired and revoked licenses are ignored', function () {
    Http::fake();
    License::factory()->create();
    License::factory()->expired()->create();
    License::factory()->expiringSoon(2)->revoked()->create();

    $this->artisan('licenses:check-expiring')->assertSuccessful();

    Http::assertNothingSent();
});

test('alerts stay pending while telegram is not configured or failing', function () {
    $license = License::factory()->expiringSoon(2)->create();

    config(['services.telegram.token' => null]);
    $this->artisan('licenses:check-expiring')->assertSuccessful();
    expect($license->refresh()->expiry_notified_at)->toBeNull();

    config(['services.telegram.token' => 'token']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
    $this->artisan('licenses:check-expiring')->assertSuccessful();
    expect($license->refresh()->expiry_notified_at)->toBeNull();
});

test('the check is scheduled hourly with a short overlap lock', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'licenses:check-expiring'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()->and($event->expiresAt)->toBe(30);
});
