<?php

namespace App\Services\Outcomes;

use App\Enums\OutcomeResult;

/** The measured values of one comparable outcome (all decimal strings), or an empty set for a not-evaluable one. */
final readonly class OutcomeFigures
{
    public function __construct(
        public ?string $baseline = null,
        public ?string $followup = null,
        public ?string $reference = null,
        public ?string $absoluteChange = null,
        public ?string $relativeChangePct = null,
        public ?string $pointChange = null,
        public ?OutcomeResult $result = null,
        public ?string $recoveredSharePct = null,
    ) {}

    public static function empty(): self
    {
        return new self(result: OutcomeResult::NotEvaluable);
    }
}
