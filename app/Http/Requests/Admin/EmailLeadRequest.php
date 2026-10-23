<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeadType;
use App\Models\EmailLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'number' => preg_replace('/\s+/', '', (string) $this->input('number')),
            'number_range' => trim((string) $this->input('number_range')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ?EmailLead $lead */
        $lead = $this->route('lead');

        return [
            'number' => [
                'required', 'string', 'max:32',
                Rule::unique('email_leads', 'number')
                    ->where('type', $this->input('type'))
                    ->where('number_range', $this->input('number_range'))
                    ->ignore($lead),
            ],
            'password' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LeadType::class)],
            'number_range' => ['nullable', 'string', 'max:100'],
            'license_id' => ['nullable', 'exists:licenses,id'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['number.unique' => 'This number already exists with the same type and range.'];
    }
}
