<?php

namespace App\Services\Imports;

use RuntimeException;

/**
 * A file-level rejection (before any import row exists). The message is safe to show the user.
 */
class ImportRejectedException extends RuntimeException
{
    /**
     * @param  list<string>  $details  offending or missing column names, when relevant
     */
    public function __construct(public readonly string $reason, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
