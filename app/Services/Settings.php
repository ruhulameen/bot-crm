<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Admin-editable settings stored in the database. A saved value overrides the matching config
 * entry (which itself falls back to .env), so the rest of the app keeps reading config().
 */
class Settings
{
    /** @var array<string, string> Setting key => config path it overrides. */
    public const CONFIG_PATHS = [
        'telegram_bot_token' => 'services.telegram.token',
        'telegram_chat_id' => 'services.telegram.chat_id',
        'online_threshold_minutes' => 'licenses.online_threshold_minutes',
        'expiring_soon_days' => 'licenses.expiring_soon_days',
    ];

    /** @var list<string> Stored encrypted. */
    private const SECRETS = ['telegram_bot_token'];

    /** @var list<string> Cast to int when applied to config. */
    private const INTEGERS = ['online_threshold_minutes', 'expiring_soon_days'];

    /** @var array<string, mixed> Config values before any override (from .env / defaults). */
    private array $defaults = [];

    public function __construct()
    {
        foreach (self::CONFIG_PATHS as $key => $path) {
            $this->defaults[$key] = config($path);
        }
    }

    public function get(string $key): ?string
    {
        $raw = $this->all()[$key] ?? null;

        if ($raw === null || ! in_array($key, self::SECRETS, true)) {
            return $raw;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Save a value, or remove it when blank so the .env default applies again.
     */
    public function set(string $key, ?string $value): void
    {
        if (blank($value)) {
            Setting::whereKey($key)->delete();
        } else {
            Setting::updateOrCreate(['key' => $key], [
                'value' => in_array($key, self::SECRETS, true) ? Crypt::encryptString($value) : $value,
            ]);
        }

        Cache::forget('settings');
        $this->apply();
    }

    /**
     * Push saved values into config (and restore defaults for removed ones).
     */
    public function apply(): void
    {
        foreach (self::CONFIG_PATHS as $key => $path) {
            $value = $this->get($key);

            config([$path => match (true) {
                $value === null => $this->defaults[$key],
                in_array($key, self::INTEGERS, true) => (int) $value,
                default => $value,
            }]);
        }
    }

    /**
     * Last characters of a secret, so admins can recognise which one is saved without revealing it.
     */
    public function hint(string $key): ?string
    {
        $value = $this->get($key);

        return $value === null ? null : '…'.substr($value, -4);
    }

    /**
     * @return array<string, string>
     */
    private function all(): array
    {
        return Cache::rememberForever('settings', fn () => Setting::pluck('value', 'key')->all());
    }
}
