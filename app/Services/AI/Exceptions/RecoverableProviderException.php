<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

/**
 * A provider failure worth retrying later by the queue: timeout, connection failure, 429 or 5xx.
 * Carries only a fixed code; never a key, a request or response body or evidence.
 */
final class RecoverableProviderException extends RuntimeException
{
    public const CODES = ['timeout', 'rate_limited', 'server_error'];

    public function __construct(private readonly string $errorCode)
    {
        parent::__construct('AI provider temporarily unavailable ('.$errorCode.').');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
