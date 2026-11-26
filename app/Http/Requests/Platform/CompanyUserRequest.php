<?php

namespace App\Http\Requests\Platform;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validation for adding and editing a company user (store and update share it). */
class CompanyUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the `platform` middleware already admitted a platform admin
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Company $company */
        $company = $this->route('company');
        $existing = $this->route('user');
        $isCreate = ! $existing instanceof User;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($existing?->id)],
            'role' => ['required', Rule::in(Role::values())],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department_id' => [
                Rule::requiredIf(fn () => in_array($this->input('role'), [Role::Manager->value, Role::Staff->value], true)),
                'nullable', 'integer', Rule::exists('departments', 'id')->where('company_id', $company->id),
            ],
            'manager_id' => [
                Rule::requiredIf(fn () => $this->input('role') === Role::Staff->value),
                'nullable', 'integer',
            ],
            'password' => $isCreate ? ['required', 'string', 'min:12', 'max:255'] : ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already used by another user.',
            'password.min' => 'The password must be at least 12 characters.',
            'department_id.required' => 'Choose a department for a Manager or Staff user.',
            'manager_id.required' => 'Choose the Manager this Staff user reports to.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('role') !== Role::Staff->value || ! $this->filled('manager_id') || ! $this->filled('department_id')) {
                return;
            }

            /** @var Company $company */
            $company = $this->route('company');
            $manager = User::query()->where('company_id', $company->id)->where('role', Role::Manager->value)->where('is_active', true)
                ->where('department_id', (int) $this->input('department_id'))->whereKey((int) $this->input('manager_id'))->exists();

            if (! $manager) {
                $validator->errors()->add('manager_id', 'Choose an active Manager of the same department.');
            }
        }];
    }
}
