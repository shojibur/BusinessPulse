<?php

namespace App\Events\Intelligence;

final class IssueStatusChanged extends IntelligenceEvent
{
    public function __construct(
        int $departmentId,
        public readonly int $issueId,
        public readonly string $from,
        public readonly string $to,
        ?int $actorUserId = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($departmentId, $actorUserId);
    }
}
