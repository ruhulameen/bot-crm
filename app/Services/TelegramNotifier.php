<?php

namespace App\Services;

use App\Exceptions\TelegramException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.chat_id'));
    }

    /**
     * Send a plain-text message to the admin chat.
     * Returns false (never throws) when Telegram is not configured or unreachable, so callers can retry later.
     */
    public function send(string $message): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(10)->post(
                'https://api.telegram.org/bot'.config('services.telegram.token').'/sendMessage',
                ['chat_id' => config('services.telegram.chat_id'), 'text' => $message],
            );
        } catch (ConnectionException $e) {
            Log::warning('Telegram unreachable: '.$e->getMessage());

            return false;
        }

        if ($response->failed()) {
            Log::warning('Telegram rejected the message: '.$response->body());
        }

        return $response->successful();
    }

    /**
     * Chats that recently messaged the bot, to look up TELEGRAM_CHAT_ID.
     *
     * @return Collection<int, array{id: int, type: string, name: string}>
     *
     * @throws TelegramException with an admin-friendly explanation of what went wrong
     */
    public function recentChats(): Collection
    {
        if (blank(config('services.telegram.token'))) {
            throw new TelegramException('Save a bot token first.');
        }

        try {
            $response = Http::timeout(10)->get('https://api.telegram.org/bot'.config('services.telegram.token').'/getUpdates');
        } catch (ConnectionException) {
            throw new TelegramException('Could not reach Telegram. Check that this server has internet access.');
        }

        if ($response->failed()) {
            throw new TelegramException(match ($response->status()) {
                401, 404 => 'Telegram says this bot token is not valid. Copy it again from @BotFather.',
                409 => 'This bot already has a webhook set (it is probably used by another app), so Telegram will not list its chats here. '
                    .'Enter the chat ID yourself — message @userinfobot on Telegram and it replies with your ID — or create a separate bot for this app with @BotFather.',
                default => 'Telegram error: '.($response->json('description') ?? 'HTTP '.$response->status()),
            });
        }

        return collect($response->json('result', []))
            ->map(fn (array $update) => $update['message']['chat'] ?? $update['channel_post']['chat'] ?? $update['my_chat_member']['chat'] ?? null)
            ->filter()
            ->unique('id')
            ->map(fn (array $chat) => [
                'id' => $chat['id'],
                'type' => $chat['type'],
                'name' => $chat['title'] ?? (trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? '')) ?: ($chat['username'] ?? '')),
            ])
            ->values();
    }
}
