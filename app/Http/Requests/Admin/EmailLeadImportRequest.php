<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EmailLeadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:'.config('leads.import_max_kilobytes')],
            'license_id' => ['nullable', 'exists:licenses,id'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload an .xlsx or .csv file.',
            'file.max' => 'The file may not be larger than '.round(config('leads.import_max_kilobytes') / 1024).' MB.',
        ];
    }
}
