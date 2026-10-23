<?php

namespace App\Http\Resources\Api\V1;

use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin License
 */
class LicenseResource extends JsonResource
{
    /** Bots read a flat payload, so no `data` envelope. */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'valid' => true,
            'product' => $this->product->slug,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
