<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\ProviderException;

final readonly class TranscriptionResponse
{
    public function __construct(public string $text) {}

    /** @param  mixed  $json  the decoded provider body */
    public static function fromJson(mixed $json): self
    {
        if (! is_array($json) || ! is_string($json['text'] ?? null)) {
            throw new ProviderException('invalid_response');
        }

        return new self($json['text']);
    }
}
