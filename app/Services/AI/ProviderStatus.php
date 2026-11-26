<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;

/** The one status mapping for every provider: 429 and 5xx are recoverable (the queue retries), other failures are final. */
final class ProviderStatus
{
    public static function assertSuccessful(int $status, bool $successful): void
    {
        if ($status === 429) {
            throw new RecoverableProviderException('rate_limited');
        }

        if ($status >= 500) {
            throw new RecoverableProviderException('server_error');
        }

        if (! $successful) {
            throw new ProviderException('client_error');
        }
    }
}
