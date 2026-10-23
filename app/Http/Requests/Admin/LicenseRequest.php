<?php

namespace App\Http\Requests\Admin;

use App\Models\License;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => Str::lower(trim((string) $this->input('username')))]);
        }
    }

    /**
     * Product, validity and the initial password are only chosen at creation; extending and
     * resetting the password are separate actions.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        /** @var ?License $license */
        $license = $this->route('license');
        $productId = $creating ? $this->input('product_id') : $license->product_id;

        return [
            'product_id' => [$creating ? 'required' : 'prohibited', 'exists:products,id'],
            'days' => [$creating ? 'required' : 'prohibited', 'integer', 'between:1,3650'],
            'username' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9._@-]+$/',
                Rule::unique('licenses', 'username')->where('product_id', $productId)->ignore($license),
            ],
            'password' => [$creating ? 'nullable' : 'prohibited', 'string', 'min:6', 'max:100'],
            'user_id' => ['nullable', 'exists:users,id'],
            'tags' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Use lowercase letters, numbers and . _ @ - only.',
            'username.unique' => 'This username is already used for the selected product.',
        ];
    }
}
