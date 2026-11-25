<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\ProviderException;

final readonly class SpeechResponse
{
    public function __construct(public string $bytes) {}

    public static function fromBinary(string $body): self
    {
        // An error object returned with a 200 status is JSON, never audio.
        if ($body === '' || str_starts_with(ltrim($body), '{')) {
            throw new ProviderException('invalid_response');
        }

        return new self($body);
    }
}
