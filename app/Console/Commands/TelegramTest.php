<?php

namespace App\Console\Commands;

use App\Services\TelegramNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:test')]
#[Description('Send a test message to the configured Telegram chat')]
class TelegramTest extends Command
{
    public function handle(TelegramNotifier $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->components->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env first.');

            return self::FAILURE;
        }

        if (! $telegram->send('✅ '.config('app.name').': Telegram notifications are working.')) {
            $this->components->error('Telegram rejected the message. Check the token, chat id and that the bot can message the chat (see storage/logs).');

            return self::FAILURE;
        }

        $this->components->info('Test message sent.');

        return self::SUCCESS;
    }
}
