<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\TelegramException;
use App\Http\Controllers\Controller;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;

class TelegramController extends Controller
{
    public function test(TelegramNotifier $telegram): RedirectResponse
    {
        if (! $telegram->isConfigured()) {
            return back()->with('error', 'Save a bot token and chat ID first.');
        }

        return $telegram->send('✅ '.config('app.name').': Telegram notifications are working.')
            ? back()->with('status', 'Test message sent. Check your Telegram.')
            : back()->with('error', 'Telegram rejected the message. Check the bot token and chat ID, and that the bot was started or added to the chat.');
    }

    /**
     * Lists chats that messaged the bot so the admin can pick the chat ID without any URL tricks.
     */
    public function chats(TelegramNotifier $telegram): RedirectResponse
    {
        try {
            $chats = $telegram->recentChats();
        } catch (TelegramException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $chats->isEmpty()
            ? back()->with('error', 'No chats found yet. Send any message to your bot (or post in the group it is in), then try again.')
            : back()->with('chats', $chats->all());
    }
}
