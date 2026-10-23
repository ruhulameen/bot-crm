<?php

use App\Enums\UserRole;
use App\Models\License;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

function validSettings(array $overrides = []): array
{
    return [
        'telegram_bot_token' => '123456:SECRET-TOKEN-wxyz',
        'telegram_chat_id' => '987654',
        'online_threshold_minutes' => 5,
        'expiring_soon_days' => 14,
        ...$overrides,
    ];
}

test('only admins can open settings', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk();
});

test('saving settings stores them, encrypts the token and applies them immediately', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), validSettings())->assertRedirect(route('admin.settings.edit'));

    expect(Setting::find('telegram_bot_token')->value)->not->toContain('SECRET-TOKEN')
        ->and(config('services.telegram.token'))->toBe('123456:SECRET-TOKEN-wxyz')
        ->and(config('services.telegram.chat_id'))->toBe('987654')
        ->and(config('licenses.online_threshold_minutes'))->toBe(5)
        ->and(config('licenses.expiring_soon_days'))->toBe(14);
});

test('the token is never rendered back, only a short hint', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), validSettings());

    $this->actingAs($this->admin)->get(route('admin.settings.edit'))
        ->assertOk()->assertDontSee('SECRET-TOKEN')->assertSee('…wxyz')->assertSee('987654');
});

test('a blank token keeps the saved one and the remove option clears it', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), validSettings());

    $this->actingAs($this->admin)->put(route('admin.settings.update'), validSettings(['telegram_bot_token' => '']));
    expect(config('services.telegram.token'))->toBe('123456:SECRET-TOKEN-wxyz');

    $this->actingAs($this->admin)->put(route('admin.settings.update'), validSettings(['telegram_bot_token' => '', 'remove_telegram_token' => '1']));
    expect(config('services.telegram.token'))->toBeNull()->and(Setting::find('telegram_bot_token'))->toBeNull();
});

test('clearing a value falls back to the .env default', function () {
    config(['services.telegram.chat_id' => 'from-env']);
    $settings = new Settings;

    $settings->set('telegram_chat_id', '555');
    expect(config('services.telegram.chat_id'))->toBe('555');

    $settings->set('telegram_chat_id', null);
    expect(config('services.telegram.chat_id'))->toBe('from-env');
});

test('settings are validated', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), validSettings(['online_threshold_minutes' => 0, 'expiring_soon_days' => 'abc']))
        ->assertSessionHasErrors(['online_threshold_minutes', 'expiring_soon_days']);
});

test('saved settings are loaded into config on the next boot', function () {
    Setting::create(['key' => 'online_threshold_minutes', 'value' => '10']);
    Setting::create(['key' => 'telegram_chat_id', 'value' => '42']);
    Cache::forget('settings');

    (new Settings)->apply();

    expect(config('licenses.online_threshold_minutes'))->toBe(10)->and(config('services.telegram.chat_id'))->toBe('42');
});

test('the saved online threshold drives bot presence', function () {
    $license = License::factory()->offline(20)->create();
    expect($license->isOnline())->toBeFalse();

    $this->actingAs($this->admin)->put(route('admin.settings.update'), validSettings(['online_threshold_minutes' => 30]));

    expect($license->refresh()->isOnline())->toBeTrue();
});

test('the test message is sent with the saved credentials', function () {
    Http::fake();
    $this->actingAs($this->admin)->put(route('admin.settings.update'), validSettings());

    $this->actingAs($this->admin)->post(route('admin.settings.telegram.test'))->assertSessionHas('status');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/bot123456:SECRET-TOKEN-wxyz/sendMessage') && $request['chat_id'] === '987654');
});

test('the test message explains missing credentials', function () {
    Http::fake();
    config(['services.telegram.token' => null]);

    $this->actingAs($this->admin)->post(route('admin.settings.telegram.test'))->assertSessionHas('error');

    Http::assertNothingSent();
});

test('the test message explains telegram errors', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);
    config(['services.telegram.token' => 'bad', 'services.telegram.chat_id' => '1']);

    $this->actingAs($this->admin)->post(route('admin.settings.telegram.test'))->assertSessionHas('error');
});

test('find my chat lists chats to pick from', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
        ['update_id' => 1, 'message' => ['chat' => ['id' => 111, 'type' => 'private', 'first_name' => 'Jane']]],
    ]])]);
    config(['services.telegram.token' => 'token']);

    $this->actingAs($this->admin)->post(route('admin.settings.telegram.chats'))
        ->assertSessionHas('chats', [['id' => 111, 'type' => 'private', 'name' => 'Jane']]);

    $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk();
});

test('find my chat tells the admin when nobody has messaged the bot yet', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    config(['services.telegram.token' => 'token']);

    $this->actingAs($this->admin)->post(route('admin.settings.telegram.chats'))->assertSessionHas('error', fn ($m) => str_contains($m, 'No chats found'));
});

test('find my chat explains why Telegram refused', function (int $status, string $description, string $expected) {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => $description], $status)]);
    config(['services.telegram.token' => 'token']);

    $this->actingAs($this->admin)->post(route('admin.settings.telegram.chats'))
        ->assertSessionHas('error', fn ($message) => str_contains($message, $expected));
})->with([
    'invalid token' => [401, 'Unauthorized', 'token is not valid'],
    'webhook active' => [409, 'Conflict: can\'t use getUpdates method while webhook is active', 'already has a webhook'],
    'other error' => [500, 'Internal Server Error', 'Telegram error: Internal Server Error'],
]);

test('find my chat explains network failures and a missing token', function () {
    config(['services.telegram.token' => 'token']);
    Http::fake(['api.telegram.org/*' => fn () => throw new ConnectionException('timeout')]);
    $this->actingAs($this->admin)->post(route('admin.settings.telegram.chats'))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'internet access'));

    config(['services.telegram.token' => null]);
    $this->actingAs($this->admin)->post(route('admin.settings.telegram.chats'))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Save a bot token'));
});
