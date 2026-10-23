<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Exceptions\LicenseRejected;
use App\Models\License;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Signs a bot in with product + username + password. Unknown username and wrong password look identical,
 * and repeated failures for the same username and IP are blocked for a while.
 */
class LicenseAuthenticator
{
    /** Valid bcrypt hash checked for unknown usernames, so they cost the same time as wrong passwords. */
    private const DUMMY_HASH = '$2y$12$EaVGw1lgaKgKio/2ZlagCOlG9X5WaEMGKW17AxNv433JExM74kWPK';

    private const MAX_FAILED_ATTEMPTS = 10;

    /**
     * @throws LicenseRejected when the credentials are wrong or, with $requireUsable, the license is revoked or expired
     */
    public function attempt(string $product, string $username, string $password, ?string $ip, bool $requireUsable = true): License
    {
        $attempts = "license-login|{$username}|{$ip}";

        if (RateLimiter::tooManyAttempts($attempts, self::MAX_FAILED_ATTEMPTS)) {
            throw new LicenseRejected('too_many_attempts', 429);
        }

        $license = License::with(['product', 'user'])
            ->where('username', $username)
            ->whereHas('product', fn ($q) => $q->where('slug', $product)->where('is_active', true))
            ->first();

        $passwordMatches = Hash::check($password, $license?->password ?? self::DUMMY_HASH);

        if (! $license || ! $passwordMatches) {
            RateLimiter::hit($attempts, 600);

            throw new LicenseRejected('invalid_credentials', 401);
        }

        RateLimiter::clear($attempts);

        if ($requireUsable) {
            match ($license->status) {
                LicenseStatus::Revoked => throw new LicenseRejected('revoked', 403),
                LicenseStatus::Expired => throw new LicenseRejected('expired', 403),
                LicenseStatus::Active => null,
            };
        }

        return $license;
    }
}
