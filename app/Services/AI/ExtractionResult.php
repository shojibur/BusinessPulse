<?php

namespace App\Services\AI;

final readonly class ExtractionResult
{
    /**
     * @param  list<array{summary: string, excerpt: string}>  $decisions
     * @param  list<DraftCandidate>  $drafts
     */
    public function __construct(public array $decisions, public array $drafts, public int $discardedCount) {}
}
