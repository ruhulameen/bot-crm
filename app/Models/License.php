<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A product account: the bot signs in with product + username + password.
 */
#[Hidden(['password'])]
#[Fillable([
    'product_id', 'user_id', 'username', 'password', 'expires_at', 'revoked_at', 'notes',
    'last_seen_at', 'offline_notified_at', 'expiry_notified_at',
])]
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    /** @var array<int, string> Quick-pick validity presets in days. */
    public const DURATIONS = [7 => '7 days', 15 => '15 days', 30 => '30 days', 90 => '90 days', 180 => '180 days', 365 => '1 year'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'offline_notified_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Readable random password (no look-alike characters) for when an admin does not pick one.
     */
    public static function generatePassword(int $length = 12): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return collect(range(1, $length))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
    }

    public static function onlineSince(): Carbon
    {
        return now()->subMinutes(config('licenses.online_threshold_minutes'));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Replace the license's tags from a comma-separated string (case-insensitive, max 10, 30 chars each).
     */
    public function syncTagNames(?string $names): void
    {
        $ids = collect(explode(',', (string) $names))
            ->map(fn (string $name) => Str::limit(Str::lower(trim($name)), 30, ''))
            ->filter()
            ->unique()
            ->take(10)
            ->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id);

        $this->tags()->sync($ids);
    }

    /**
     * @return Attribute<LicenseStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): LicenseStatus => match (true) {
            $this->revoked_at !== null => LicenseStatus::Revoked,
            $this->expires_at->isPast() => LicenseStatus::Expired,
            default => LicenseStatus::Active,
        });
    }

    public function passwordMatches(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    public function isUsable(): bool
    {
        return $this->status === LicenseStatus::Active;
    }

    public function isExpiringSoon(): bool
    {
        return $this->isUsable() && $this->expires_at->lte(now()->addDays(config('licenses.expiring_soon_days')));
    }

    public function hasBeenSeen(): bool
    {
        return $this->last_seen_at !== null;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at?->gte(self::onlineSince()) ?? false;
    }

    /**
     * Add days on top of the current expiry, or from today when already expired (renew).
     */
    public function extendByDays(int $days): void
    {
        $base = $this->expires_at->isFuture() ? $this->expires_at : now();

        $this->update(['expires_at' => $base->copy()->addDays($days), 'expiry_notified_at' => null]);
    }

    public function extendUntil(Carbon $date): void
    {
        $this->update(['expires_at' => $date->copy()->endOfDay(), 'expiry_notified_at' => null]);
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function restore(): void
    {
        $this->update(['revoked_at' => null]);
    }

    #[Scope]
    protected function whereStatus(Builder $query, LicenseStatus $status): void
    {
        match ($status) {
            LicenseStatus::Revoked => $query->whereNotNull('revoked_at'),
            LicenseStatus::Expired => $query->whereNull('revoked_at')->where('expires_at', '<=', now()),
            LicenseStatus::Active => $query->whereNull('revoked_at')->where('expires_at', '>', now()),
        };
    }

    #[Scope]
    protected function expiringSoon(Builder $query): void
    {
        $query->whereStatus(LicenseStatus::Active)
            ->where('expires_at', '<=', now()->addDays(config('licenses.expiring_soon_days')));
    }

    #[Scope]
    protected function online(Builder $query): void
    {
        $query->where('last_seen_at', '>=', self::onlineSince());
    }

    #[Scope]
    protected function offline(Builder $query): void
    {
        $query->where('last_seen_at', '<', self::onlineSince());
    }

    /**
     * @param  array{q?: ?string, product?: ?string, tag?: ?string, status?: ?string, presence?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(
                fn (Builder $q) => $q->where('username', 'like', "%{$term}%")
                    ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ))
            ->when($filters['tag'] ?? null, fn (Builder $q, string $id) => $q->whereHas('tags', fn (Builder $t) => $t->where('tags.id', $id)))
            ->when($filters['product'] ?? null, fn (Builder $q, string $id) => $q->where('product_id', $id))
            ->when($filters['status'] ?? null, function (Builder $q, string $status) {
                if ($status === 'expiring') {
                    $q->expiringSoon();
                } elseif ($enum = LicenseStatus::tryFrom($status)) {
                    $q->whereStatus($enum);
                }
            })
            ->when($filters['presence'] ?? null, function (Builder $q, string $presence) {
                match ($presence) {
                    'online' => $q->online(),
                    'offline' => $q->offline(),
                    'never' => $q->whereNull('last_seen_at'),
                    default => null,
                };
            });
    }
}
