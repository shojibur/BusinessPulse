<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'min:12', 'max:255']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['password.min' => 'The password must be at least 12 characters.'];
    }
}
