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
        /** True when the week 8 decline was also imported (issue, urgent notifications, draft meeting). */
        public bool $populated = false,
    ) {}
}
