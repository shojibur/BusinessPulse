<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateBriefAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requestAudio', $this->route('brief')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
