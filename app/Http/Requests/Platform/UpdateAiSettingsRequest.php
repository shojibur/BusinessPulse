<?php

namespace App\Http\Requests\Platform;

use App\Rules\SafeProviderUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAiSettingsRequest extends FormRequest
{
    public const CAPABILITIES = ['text', 'transcription', 'speech'];

    public function authorize(): bool
    {
        return true; // the `platform` middleware already admitted a platform admin
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $model = ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\/-]+$/'];
        $url = ['nullable', 'string', 'max:255', new SafeProviderUrl];
        $key = ['nullable', 'string', 'max:500'];

        return [
            'text_provider' => ['required', 'in:openai,anthropic'],
            'text_base_url' => $url,
            'text_api_key' => $key,
            'text_model' => $model,
            'transcription_base_url' => $url,
            'transcription_api_key' => $key,
            'transcription_model' => $model,
            'speech_base_url' => $url,
            'speech_api_key' => $key,
            'speech_model' => $model,
            'speech_voice' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'clear_text_api_key' => ['boolean'],
            'clear_transcription_api_key' => ['boolean'],
            'clear_speech_api_key' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            '*.regex' => 'Use only letters, digits and . _ : / -',
            'text_provider.in' => 'Choose OpenAI-compatible or Claude (Anthropic).',
        ];
    }
}
