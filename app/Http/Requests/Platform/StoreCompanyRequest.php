<?php

namespace App\Http\Requests\Platform;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the `platform` middleware already admitted a platform admin
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_email' => mb_strtolower(trim((string) $this->input('owner_email'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', 'unique:companies,name'],
            'timezone' => ['required', 'string', 'in:'.implode(',', DateTimeZone::listIdentifiers())],
            'trading_days' => ['required', 'array', 'min:1'],
            'trading_days.*' => ['integer', 'between:1,7', 'distinct'],
            'branches' => ['nullable', 'string', 'max:500'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:12', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'A company with this name already exists.',
            'owner_email.unique' => 'This email is already used by another user.',
            'owner_password.min' => 'The Owner password must be at least 12 characters.',
            'trading_days.required' => 'Choose at least one trading day.',
            'trading_days.min' => 'Choose at least one trading day.',
        ];
    }

    /**
     * Branch codes: comma or newline separated, upper-case, unique, 1 to 20 letters, digits, dash or underscore.
     *
     * @return list<string>
     */
    public function branchCodes(): array
    {
        $codes = preg_split('/[\s,;]+/', strtoupper((string) $this->input('branches')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($codes));
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach ($this->branchCodes() as $code) {
                if (preg_match('/^[A-Z0-9_-]{1,20}$/', $code) !== 1) {
                    $validator->errors()->add('branches', "Branch code [{$code}] must be 1 to 20 letters, digits, dashes or underscores.");

                    return;
                }
            }
        }];
    }
}
