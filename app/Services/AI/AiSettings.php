<?php

namespace App\Services\AI;

use App\Models\AiSetting;

/**
 * Resolves the AI settings used across the whole site: the platform settings first (S11), then the `.env` values of
 * config/ai.php. Read on every call (one tiny indexed row), so a change made by a platform admin applies to the very
 * next request or job without restarting workers.
 *
 * Keys never cross provider families: an OpenAI key from `.env` is never sent to Anthropic and the other way round.
 */
class AiSettings
{
    public const ANTHROPIC_DEFAULT_URL = 'https://api.anthropic.com';

    /** The demo seeder pins every endpoint to "no AI" or to the recorded sample so seeding never reaches a live provider. */
    public const MODE_OFF = 'off';

    public const MODE_SAMPLE = 'sample';

    public const SAMPLE_URL = 'https://demo-sample.invalid/v1';

    private static ?string $pinned = null;

    public static function pin(?string $mode): void
    {
        self::$pinned = $mode;
    }

    private function pinnedEndpoint(string $model, ?string $voice = null, bool $needsVoice = false): ?AiEndpoint
    {
        if (self::$pinned === null) {
            return null;
        }

        $sample = self::$pinned === self::MODE_SAMPLE;

        return new AiEndpoint(AiEndpoint::PROVIDER_OPENAI, $sample ? self::SAMPLE_URL : null, $sample ? 'demo-sample-key' : null, $model, $voice, source: $sample ? 'env' : 'none', needsVoice: $needsVoice);
    }

    public function text(): AiEndpoint
    {
        if (($pinned = $this->pinnedEndpoint('demo-sample-model')) !== null) {
            return $pinned;
        }

        $row = $this->row();
        $provider = self::provider($row?->text_provider ?: config('ai.text_provider'));

        if ($provider === AiEndpoint::PROVIDER_ANTHROPIC) {
            $key = self::value($row?->text_api_key) ?? self::value(config('ai.anthropic_api_key'));

            return new AiEndpoint(
                $provider,
                self::value($row?->text_base_url) ?? self::ANTHROPIC_DEFAULT_URL,
                $key,
                self::value($row?->text_model) ?? self::value(config('ai.text_model')),
                source: self::value($row?->text_api_key) !== null ? 'platform' : ($key !== null ? 'env' : 'none'),
            );
        }

        $key = self::value($row?->text_api_key) ?? self::value(config('ai.api_key'));

        return new AiEndpoint(
            $provider,
            self::value($row?->text_base_url) ?? self::value(config('ai.base_url')),
            $key,
            self::value($row?->text_model) ?? self::value(config('ai.text_model')),
            source: self::value($row?->text_api_key) !== null ? 'platform' : ($key !== null ? 'env' : 'none'),
        );
    }

    public function transcription(): AiEndpoint
    {
        if (($pinned = $this->pinnedEndpoint('demo-sample-model')) !== null) {
            return $pinned;
        }

        $row = $this->row();
        $key = self::value($row?->transcription_api_key) ?? self::value(config('ai.api_key'));

        return new AiEndpoint(
            AiEndpoint::PROVIDER_OPENAI,
            self::value($row?->transcription_base_url) ?? self::value(config('ai.base_url')),
            $key,
            self::value($row?->transcription_model) ?? self::value(config('ai.transcription_model')),
            source: self::value($row?->transcription_api_key) !== null ? 'platform' : ($key !== null ? 'env' : 'none'),
        );
    }

    public function speech(): AiEndpoint
    {
        if (($pinned = $this->pinnedEndpoint('demo-sample-model', 'alloy', true)) !== null) {
            return $pinned;
        }

        $row = $this->row();
        $key = self::value($row?->speech_api_key) ?? self::value(config('ai.api_key'));

        return new AiEndpoint(
            AiEndpoint::PROVIDER_OPENAI,
            self::value($row?->speech_base_url) ?? self::value(config('ai.base_url')),
            $key,
            self::value($row?->speech_model) ?? self::value(config('ai.speech_model')),
            self::value($row?->speech_voice) ?? self::value(config('ai.speech_voice')),
            source: self::value($row?->speech_api_key) !== null ? 'platform' : ($key !== null ? 'env' : 'none'),
            needsVoice: true,
        );
    }

    public static function provider(mixed $value): string
    {
        return $value === AiEndpoint::PROVIDER_ANTHROPIC ? AiEndpoint::PROVIDER_ANTHROPIC : AiEndpoint::PROVIDER_OPENAI;
    }

    private function row(): ?AiSetting
    {
        return AiSetting::query()->orderBy('id')->first();
    }

    private static function value(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
