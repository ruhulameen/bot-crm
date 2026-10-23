<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Plain-text Telegram messages. Every account is described with product, username and customer so an
 * alert can be identified without opening the panel. Expects `product` and `user` to be loaded.
 */
class LicenseAlertMessages
{
    public static function firstLogin(License $license): string
    {
        return "🔔 First login\n".self::describe($license, expiry: true);
    }

    public static function backOnline(License $license, ?Carbon $offlineSince = null): string
    {
        $downtime = $offlineSince ? "\nWas offline for: ".$offlineSince->diffForHumans(now(), true) : '';

        return "✅ Back online\n".self::describe($license, expiry: true).$downtime;
    }

    /**
     * @param  Collection<int, License>  $licenses
     */
    public static function offline(Collection $licenses): string
    {
        return "⚠️ {$licenses->count()} account(s) went offline\n\n".$licenses
            ->map(fn (License $license) => self::describe($license, lastSeen: true, expiry: true))
            ->implode("\n\n");
    }

    /**
     * @param  Collection<int, License>  $licenses
     */
    public static function expiring(Collection $licenses): string
    {
        return "⏳ {$licenses->count()} license(s) expiring within ".config('licenses.expiring_soon_days')." days\n\n".$licenses
            ->map(fn (License $license) => self::describe($license, lastSeen: true, expiry: true))
            ->implode("\n\n");
    }

    private static function describe(License $license, bool $lastSeen = false, bool $expiry = false): string
    {
        $lines = [
            "Product: {$license->product->name}",
            "Username: {$license->username}",
            'Customer: '.($license->user ? "{$license->user->name} ({$license->user->email})" : 'none'),
        ];

        if ($lastSeen) {
            $lines[] = 'Last seen: '.($license->last_seen_at ? self::when($license->last_seen_at) : 'never');
        }

        if ($expiry) {
            $lines[] = 'Expires: '.self::when($license->expires_at);
        }

        return implode("\n", $lines);
    }

    private static function when(Carbon $date): string
    {
        return $date->format('d M Y, H:i T').' ('.$date->diffForHumans().')';
    }
}
