<?php

namespace App\Services\AI;

use App\Services\Meetings\AssigneeResult;
use App\Services\Meetings\DeadlineResult;

final readonly class DraftCandidate
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $rawAssigneeName,
        public AssigneeResult $assignee,
        public ?string $rawDeadlinePhrase,
        public DeadlineResult $deadline,
        public string $excerpt,
    ) {}
}
