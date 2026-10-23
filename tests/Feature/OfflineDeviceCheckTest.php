<?php

use App\Jobs\SendTelegramMessage;
use App\Models\License;
use Database\Factories\LicenseFactory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.telegram.token' => 'token', 'services.telegram.chat_id' => '123']);
});

test('offline accounts are reported once to telegram', function () {
    Http::fake();
    $license = License::factory()->offline()->create(['username' => 'lost-bot']);

    $this->artisan('licenses:check-offline')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org/bottoken/sendMessage')
        && $request['chat_id'] === '123'
        && str_contains($request['text'], 'lost-bot'));
    expect($license->refresh()->offline_notified_at)->not->toBeNull();

    $this->artisan('licenses:check-offline')->assertSuccessful();
    Http::assertSentCount(1);
});

test('online, never-seen and unusable accounts are ignored', function () {
    Http::fake();
    License::factory()->online()->create();
    License::factory()->create();
    License::factory()->revoked()->offline()->create();
    License::factory()->expired()->offline()->create();

    $this->artisan('licenses:check-offline')->assertSuccessful();

    Http::assertNothingSent();
});

test('alerts stay pending while telegram is not configured or failing, then go out later', function () {
    $license = License::factory()->offline()->create();

    Http::fake();
    config(['services.telegram.token' => null]);
    $this->artisan('licenses:check-offline')->assertSuccessful();
    Http::assertNothingSent();
    expect($license->refresh()->offline_notified_at)->toBeNull();

    config(['services.telegram.token' => 'token']);
    $this->artisan('licenses:check-offline')->assertSuccessful();
    expect($license->refresh()->offline_notified_at)->not->toBeNull();
});

test('the check is scheduled every minute with a short overlap lock', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'licenses:check-offline'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()->and($event->expiresAt)->toBe(5);
});

test('a bot that goes down again after recovering is reported again, every time', function () {
    Http::fake();
    Bus::fake([SendTelegramMessage::class]);
    $license = License::factory()->offline()->create();
    $credentials = ['product' => $license->product->slug, 'username' => $license->username, 'password' => LicenseFactory::PASSWORD];

    foreach ([1, 2, 3] as $outage) {
        $this->artisan('licenses:check-offline')->assertSuccessful();
        expect($license->refresh()->offline_notified_at)->not->toBeNull();

        $this->postJson(route('api.v1.licenses.heartbeat'), $credentials)->assertOk();
        expect($license->refresh()->offline_notified_at)->toBeNull();

        $license->update(['last_seen_at' => now()->subMinutes(30)]);
    }

    Http::assertSentCount(3);
    Bus::assertDispatchedAfterResponseTimes(SendTelegramMessage::class, 3);
});

test('a bot that restarts through login re-arms the offline alert and says it is back', function () {
    Http::fake();
    Bus::fake([SendTelegramMessage::class]);
    $license = License::factory()->offline()->create();
    $credentials = ['product' => $license->product->slug, 'username' => $license->username, 'password' => LicenseFactory::PASSWORD];

    $this->artisan('licenses:check-offline')->assertSuccessful();
    Http::assertSentCount(1);

    $this->postJson(route('api.v1.licenses.login'), $credentials)->assertOk();
    expect($license->refresh()->offline_notified_at)->toBeNull();
    Bus::assertDispatchedAfterResponse(SendTelegramMessage::class, fn ($job) => str_contains($job->message, 'Back online'));

    $license->update(['last_seen_at' => now()->subMinutes(30)]);
    $this->artisan('licenses:check-offline')->assertSuccessful();
    Http::assertSentCount(2);
});
