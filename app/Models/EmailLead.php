<?php

namespace App\Models;

use App\Enums\LeadType;
use Database\Factories\EmailLeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone number lead with its password, grouped by type and number range, tagged with who supplied it and when.
 */
#[Fillable(['number', 'password', 'type', 'number_range', 'license_id', 'user_id', 'username', 'source'])]
class EmailLead extends Model
{
    /** @use HasFactory<EmailLeadFactory> */
    use HasFactory;

    public const SOURCES = ['manual', 'import', 'api'];

    protected function casts(): array
    {
        return [
            'type' => LeadType::class,
            'password' => 'encrypted',
        ];
    }

    /**
     * Tag columns for a lead supplied by a bot account (or entered on its behalf).
     *
     * @return array{license_id: ?int, user_id: ?int, username: ?string}
     */
    public static function tagsFor(?License $license, ?int $userId = null): array
    {
        return [
            'license_id' => $license?->id,
            'user_id' => $userId ?? $license?->user_id,
            'username' => $license?->username,
        ];
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array{q?: ?string, type?: ?string, types?: ?list<string>, range?: ?string, username?: ?string, user?: ?string, from?: ?string, to?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(
                fn (Builder $q) => $q->where('number', 'like', "%{$term}%")
                    ->orWhere('number_range', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
            ))
            ->when(LeadType::tryFrom($filters['type'] ?? ''), fn (Builder $q, LeadType $type) => $q->where('type', $type))
            ->when($filters['types'] ?? null, fn (Builder $q, array $types) => $q->whereIn('type', $types))
            ->when($filters['range'] ?? null, fn (Builder $q, string $range) => $q->where('number_range', $range))
            ->when($filters['username'] ?? null, fn (Builder $q, string $username) => $q->where('username', $username))
            ->when($filters['user'] ?? null, fn (Builder $q, string $id) => $q->where('user_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $q, string $date) => $q->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $q, string $date) => $q->where('created_at', '<=', $date.' 23:59:59'));
    }
}
