<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\ProviderException;

final readonly class SpeechResponse
{
    public function __construct(public string $bytes) {}

    public static function fromBinary(string $body): self
    {
        // An error object returned with a 200 status is JSON, never audio. The requested format is mp3: an ID3 tag
        // or an MPEG frame sync (0xFFEx/0xFFFx) must open the body.
        $mp3 = str_starts_with($body, 'ID3') || (strlen($body) > 2 && ord($body[0]) === 0xFF && (ord($body[1]) & 0xE0) === 0xE0);

        if (strlen($body) < 4 || ! $mp3) {
            throw new ProviderException('invalid_response');
        }

        return new self($body);
    }
}
