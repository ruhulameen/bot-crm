<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('admin.settings.edit', [
            'tokenHint' => config('services.telegram.token') ? ($settings->hint('telegram_bot_token') ?? 'from .env') : null,
            'chatId' => config('services.telegram.chat_id'),
            'onlineThreshold' => config('licenses.online_threshold_minutes'),
            'expiringDays' => config('licenses.expiring_soon_days'),
        ]);
    }

    public function update(SettingRequest $request, Settings $settings): RedirectResponse
    {
        $data = $request->validated();

        if ($request->boolean('remove_telegram_token')) {
            $settings->set('telegram_bot_token', null);
        } elseif (filled($data['telegram_bot_token'] ?? null)) {
            $settings->set('telegram_bot_token', trim($data['telegram_bot_token']));
        }

        $settings->set('telegram_chat_id', filled($data['telegram_chat_id'] ?? null) ? trim($data['telegram_chat_id']) : null);
        $settings->set('online_threshold_minutes', (string) $data['online_threshold_minutes']);
        $settings->set('expiring_soon_days', (string) $data['expiring_soon_days']);

        return to_route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
