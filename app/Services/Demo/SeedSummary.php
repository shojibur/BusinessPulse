<?php

namespace App\Services\Demo;

final readonly class SeedSummary
{
    /** @param  array<string, int>  $importCounters */
    public function __construct(
        public bool $alreadySeeded,
        public int $users,
        public string $ruleOverride,
        public array $importCounters,
        public string $clockDateTime,
    ) {}
}
