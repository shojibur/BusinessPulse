<?php

namespace App\Services\Outcomes;

use RuntimeException;

/** At least one issue could not be verified in this run; the job retries (every step is idempotent). Carries a count only. */
final class VerificationIncomplete extends RuntimeException
{
    public function __construct(public readonly int $failures)
    {
        parent::__construct("Outcome verification incomplete ({$failures} issue(s)).");
    }
}
