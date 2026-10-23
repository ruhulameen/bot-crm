<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A bot request that must be refused. Renders itself as the JSON answer the bot expects.
 */
class LicenseRejected extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status)
    {
        parent::__construct($reason);
    }

    public function render(): JsonResponse
    {
        return response()->json(['valid' => false, 'reason' => $this->reason], $this->status);
    }
}
