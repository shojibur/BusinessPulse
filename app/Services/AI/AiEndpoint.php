<?php

namespace App\Services\AI;

/**
 * One resolved AI capability (text, transcription or speech): where to call, with which key and model, and where the
 * values came from. The key is only ever used server-side to call the provider.
 */
final readonly class AiEndpoint
{
    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_ANTHROPIC = 'anthropic';

    public function __construct(
        public string $provider,
        public ?string $baseUrl,
        public ?string $apiKey,
        public ?string $model,
        public ?string $voice = null,
        /** 'platform' when the key comes from the platform settings, 'env' from `.env`, 'none' when there is no key. */
        public string $source = 'none',
        public bool $needsVoice = false,
    ) {}

    public function configured(): bool
    {
        return self::filled($this->baseUrl) && self::filled($this->apiKey) && self::filled($this->model)
            && (! $this->needsVoice || self::filled($this->voice));
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
