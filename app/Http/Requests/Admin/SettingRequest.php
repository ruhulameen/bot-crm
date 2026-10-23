<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'remove_telegram_token' => ['boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64'],
            'online_threshold_minutes' => ['required', 'integer', 'between:1,120'],
            'expiring_soon_days' => ['required', 'integer', 'between:1,365'],
        ];
    }
}
