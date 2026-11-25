<?php

namespace App\Http\Requests;

use App\Models\Brief;
use Illuminate\Foundation\Http\FormRequest;

class GenerateBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brief::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
