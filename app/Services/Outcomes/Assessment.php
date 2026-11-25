<?php

namespace App\Services\Outcomes;

use App\Enums\NotEvaluableReason;

/**
 * @phpstan-type Check array{name: string, state: string}
 */
final readonly class Assessment
{
    /**
     * @param  list<array{name: string, state: string}>  $checks
     * @param  array<string, mixed>|null  $week
     */
    public function __construct(public array $checks, public ?NotEvaluableReason $failedReason, public ?array $week) {}

    public function comparable(): bool
    {
        return $this->failedReason === null;
    }
}
