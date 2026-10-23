<?php

namespace App\Jobs;

use App\Services\TelegramNotifier;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Dispatch with `dispatchAfterResponse()` so bot-facing API calls never wait on Telegram.
 */
class SendTelegramMessage
{
    use Dispatchable;

    public function __construct(public string $message) {}

    public function handle(TelegramNotifier $telegram): void
    {
        $telegram->send($this->message);
    }
}
