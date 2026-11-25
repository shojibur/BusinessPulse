<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

/**
 * A provider failure that retrying cannot fix: a 4xx answer or an unusable response. Fixed codes only.
 */
final class ProviderException extends RuntimeException
{
    public function __construct(private readonly string $errorCode)
    {
        parent::__construct('AI provider request failed ('.$errorCode.').');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
