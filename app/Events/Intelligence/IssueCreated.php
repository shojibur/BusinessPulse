<?php

namespace App\Events\Intelligence;

final class IssueCreated extends IntelligenceEvent
{
    public function __construct(
        int $departmentId,
        public readonly int $issueId,
        public readonly string $severity,
        public readonly ?int $recurrenceOfIssueId = null,
        ?int $actorUserId = null,
    ) {
        parent::__construct($departmentId, $actorUserId);
    }
}
