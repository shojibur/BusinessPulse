<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\ProviderException;

final readonly class ChatResponse
{
    /** @param  array<string, mixed>  $data  the decoded structured answer */
    public function __construct(public array $data, public string $content) {}

    /**
     * @param  mixed  $json  the decoded provider body
     */
    public static function fromJson(mixed $json): self
    {
        $content = is_array($json) ? ($json['choices'][0]['message']['content'] ?? null) : null;

        if (! is_string($content) || $content === '') {
            throw new ProviderException('invalid_response');
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new ProviderException('invalid_response');
        }

        return new self($data, $content);
    }
}
