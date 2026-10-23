<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    /** Plain-text password every factory-made account is created with. */
    public const PASSWORD = 'secret-pass';

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'username' => str(fake()->unique()->userName())->lower()->replaceMatches('/[^a-z0-9._-]/', '')->toString(),
            'password' => self::PASSWORD,
            'expires_at' => now()->addDays(30),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function expiringSoon(int $days = 3): static
    {
        return $this->state(fn () => ['expires_at' => now()->addDays($days)]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    /**
     * The bot signed in a moment ago.
     */
    public function online(): static
    {
        return $this->state(fn () => ['last_seen_at' => now()]);
    }

    /**
     * The bot stopped sending heartbeats this many minutes ago.
     */
    public function offline(int $minutes = 30): static
    {
        return $this->state(fn () => ['last_seen_at' => now()->subMinutes($minutes)]);
    }
}
