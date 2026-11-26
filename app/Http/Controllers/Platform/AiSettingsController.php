<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\TestAiConnection;
use App\Actions\Platform\UpdateAiSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateAiSettingsRequest;
use App\Models\AiSetting;
use App\Services\AI\AiEndpoint;
use App\Services\AI\AiSettings;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform panel's AI settings page (S11). It shows values and which source each capability uses, and whether a key
 * is saved, but never a key itself: keys are write-only.
 */
class AiSettingsController extends Controller
{
    public function edit(AiSettings $settings): Response
    {
        $row = AiSetting::query()->orderBy('id')->first();
        $text = $settings->text();
        $transcription = $settings->transcription();
        $speech = $settings->speech();

        return Inertia::render('platform/AiSettings', [
            'values' => [
                'text_provider' => $row?->text_provider ?? $text->provider,
                'text_base_url' => $row?->text_base_url,
                'text_model' => $row?->text_model,
                'transcription_base_url' => $row?->transcription_base_url,
                'transcription_model' => $row?->transcription_model,
                'speech_base_url' => $row?->speech_base_url,
                'speech_model' => $row?->speech_model,
                'speech_voice' => $row?->speech_voice,
            ],
            'keys' => [
                'text' => $row?->text_api_key !== null,
                'transcription' => $row?->transcription_api_key !== null,
                'speech' => $row?->speech_api_key !== null,
            ],
            'status' => [
                'text' => $this->status($text),
                'transcription' => $this->status($transcription),
                'speech' => $this->status($speech),
            ],
            'examples' => ['claude' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'], 'anthropic_url' => AiSettings::ANTHROPIC_DEFAULT_URL],
        ]);
    }

    public function update(UpdateAiSettingsRequest $request, UpdateAiSettings $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());
        Flash::success('AI settings saved. They apply to the whole site from the next request.');

        return to_route('platform.ai.edit');
    }

    public function test(Request $request, TestAiConnection $action): RedirectResponse
    {
        $result = $action->handle($request->user());
        $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);

        return to_route('platform.ai.edit');
    }

    /** @return array{configured: bool, source: string, provider: string, model: string|null} */
    private function status(AiEndpoint $endpoint): array
    {
        return [
            'configured' => $endpoint->configured(),
            'source' => $endpoint->source,
            'provider' => $endpoint->provider,
            'model' => $endpoint->model,
        ];
    }
}
