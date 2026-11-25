<?php

namespace App\Services\Intelligence;

use RuntimeException;

/**
 * A lifecycle transition that is not in the allowed table. The issue is left unchanged.
 */
class IssueTransitionNotAllowedException extends RuntimeException
{
    public function __construct(public readonly string $from, public readonly string $to)
    {
        parent::__construct("An issue cannot move from {$from} to {$to}.");
    }
}
