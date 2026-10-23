<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ExtendLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Either add a number of days or set a custom expiry date.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'days' => ['nullable', 'required_without:expires_at', 'integer', 'between:1,3650'],
            'expires_at' => ['nullable', 'required_without:days', 'date', 'after:now'],
        ];
    }
}
