<?php

namespace App\Services\Demo;

final readonly class ResetSummary
{
    /**
     * @param  array<string, int>  $deleted  rows deleted per group
     * @param  list<string>  $undeletedFiles
     */
    public function __construct(public array $deleted, public int $filesDeleted, public array $undeletedFiles, public SeedSummary $seed, public int $failedJobs) {}
}
