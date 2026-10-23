<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeadType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailLeadExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Everything is optional: with no dates and no types the export contains every lead.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:csv,xlsx'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::enum(LeadType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
            'types.*' => 'Pick a valid lead type.',
        ];
    }

    /**
     * Name that tells what the file contains, e.g. email-leads-bell-otp-on-2026-03-01-20260301-103000.xlsx.
     */
    public function filename(string $format): string
    {
        $from = $this->validated('date_from');
        $to = $this->validated('date_to');

        $dates = match (true) {
            $from && $to && $from === $to => "on-{$from}",
            (bool) $from && (bool) $to => "{$from}-to-{$to}",
            (bool) $from => "from-{$from}",
            (bool) $to => "until-{$to}",
            default => null,
        };

        return collect(['email-leads', implode('-', $this->validated('types') ?? []) ?: null, $dates, now()->format('Ymd-His')])
            ->filter()
            ->implode('-').".{$format}";
    }
}
