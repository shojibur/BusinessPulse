<?php

namespace App\Events\Intelligence;

final class UrgentIssueNotified extends IntelligenceEvent
{
    /**
     * @param  list<int>  $recipientUserIds
     */
    public function __construct(
        int $departmentId,
        public readonly int $issueId,
        public readonly string $severity,
        public readonly string $dedupeKey,
        public readonly array $recipientUserIds,
    ) {
        parent::__construct($departmentId, null);
    }
}
