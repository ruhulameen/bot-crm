<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResetLicensePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A blank password means "generate one".
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['password' => ['nullable', 'string', 'min:6', 'max:100']];
    }
}
