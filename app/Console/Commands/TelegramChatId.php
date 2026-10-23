<?php

namespace App\Console\Commands;

use App\Exceptions\TelegramException;
use App\Services\TelegramNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:chat-id')]
#[Description('List chats that messaged the bot, to find the value for TELEGRAM_CHAT_ID')]
class TelegramChatId extends Command
{
    public function handle(TelegramNotifier $telegram): int
    {
        try {
            $chats = $telegram->recentChats();
        } catch (TelegramException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($chats->isEmpty()) {
            $this->components->warn('No chats yet. Send any message to your bot (or post in the group it was added to), then run this again.');

            return self::FAILURE;
        }

        $this->table(['TELEGRAM_CHAT_ID', 'Type', 'Name'], $chats->all());

        return self::SUCCESS;
    }
}
