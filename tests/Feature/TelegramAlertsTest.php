<?php

use App\Jobs\SendTelegramMessage;
use App\Models\License;
use Database\Factories\LicenseFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.telegram.token' => 'token', 'services.telegram.chat_id' => '123']);
});

function botCredentials(License $license): array
{
    return ['product' => $license->product->slug, 'username' => $license->username, 'password' => LicenseFactory::PASSWORD];
}

test('telegram:test sends a message when configured', function () {
    Http::fake();

    $this->artisan('telegram:test')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/bottoken/sendMessage') && $request['chat_id'] === '123');
});

test('telegram:test fails when telegram rejects the message', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);

    $this->artisan('telegram:test')->assertFailed();
});

test('telegram:test fails when telegram is not configured', function () {
    Http::fake();
    config(['services.telegram.chat_id' => null]);

    $this->artisan('telegram:test')->assertFailed();
    Http::assertNothingSent();
});

test('an unreachable telegram does not throw', function () {
    Http::fake(['api.telegram.org/*' => fn () => throw new ConnectionException('timeout')]);

    $this->artisan('telegram:test')->assertFailed();
});

test('the job delivers its message through the notifier', function () {
    Http::fake();

    SendTelegramMessage::dispatchSync('hello admin');

    Http::assertSent(fn ($request) => $request['text'] === 'hello admin');
});

test('the first login queues one notification, later logins and heartbeats do not', function () {
    Bus::fake();
    $license = License::factory()->create();

    $this->postJson(route('api.v1.licenses.login'), botCredentials($license))->assertOk();
    Bus::assertDispatchedAfterResponse(SendTelegramMessage::class, fn ($job) => str_contains($job->message, 'First login') && str_contains($job->message, $license->username));

    Bus::fake();
    $this->postJson(route('api.v1.licenses.login'), botCredentials($license))->assertOk();
    $this->postJson(route('api.v1.licenses.heartbeat'), botCredentials($license))->assertOk();
    Bus::assertNothingDispatched();
});

test('a heartbeat as the very first contact does not announce a first login', function () {
    Bus::fake();
    $license = License::factory()->create();

    $this->postJson(route('api.v1.licenses.heartbeat'), botCredentials($license))->assertOk();

    Bus::assertNothingDispatched();
});

test('a bot returning after an offline alert queues one back-online message, via heartbeat or login', function (string $route) {
    Bus::fake();
    $license = License::factory()->offline()->create(['offline_notified_at' => now()]);

    $this->postJson(route($route), botCredentials($license))->assertOk();
    Bus::assertDispatchedAfterResponse(SendTelegramMessage::class, fn ($job) => str_contains($job->message, 'Back online'));

    Bus::fake();
    $this->postJson(route($route), botCredentials($license))->assertOk();
    Bus::assertNothingDispatched();
})->with(['api.v1.licenses.heartbeat', 'api.v1.licenses.login']);

test('regular heartbeats stay silent', function () {
    Bus::fake();
    $license = License::factory()->online()->create();

    $this->postJson(route('api.v1.licenses.heartbeat'), botCredentials($license))->assertOk();

    Bus::assertNothingDispatched();
});

test('the bot api still succeeds when telegram is down', function () {
    Http::fake(['api.telegram.org/*' => fn () => throw new ConnectionException('down')]);
    $license = License::factory()->create();

    $this->postJson(route('api.v1.licenses.login'), botCredentials($license))->assertOk()->assertJson(['valid' => true]);
});

test('telegram:chat-id lists the chats that messaged the bot', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
        ['update_id' => 1, 'message' => ['chat' => ['id' => 111, 'type' => 'private', 'first_name' => 'Jane', 'last_name' => 'Doe']]],
        ['update_id' => 2, 'message' => ['chat' => ['id' => 111, 'type' => 'private', 'first_name' => 'Jane']]],
        ['update_id' => 3, 'message' => ['chat' => ['id' => -222, 'type' => 'group', 'title' => 'Ops']]],
    ]])]);

    $this->artisan('telegram:chat-id')->expectsOutputToContain('111')->expectsOutputToContain('Ops')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/bottoken/getUpdates'));
});

test('telegram:chat-id explains what to do when nobody messaged the bot yet', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

    $this->artisan('telegram:chat-id')->expectsOutputToContain('No chats yet')->assertFailed();
});

test('telegram:chat-id fails clearly without a token', function () {
    config(['services.telegram.token' => null]);

    $this->artisan('telegram:chat-id')->expectsOutputToContain('Save a bot token')->assertFailed();
});

test('telegram:chat-id explains an active webhook instead of failing silently', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Conflict'], 409)]);

    $this->artisan('telegram:chat-id')->expectsOutputToContain('already has a webhook')->assertFailed();
});
