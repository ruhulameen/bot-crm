<?php

namespace App\Http\Requests\Api\V1;

class StoreEmailLeadsRequest extends LicenseAuthRequest
{
    /**
     * Account credentials plus the leads to store. Each lead is validated by the importer, so one bad
     * row is reported back instead of failing the whole batch.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'leads' => ['required', 'array', 'min:1', 'max:'.config('leads.api_max_per_request')],
            'leads.*' => ['array'],
        ];
    }
}
