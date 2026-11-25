<?php

namespace App\Services\Activity;

use App\Enums\ActivityAction;
use App\Enums\ActivitySubjectType;
use Carbon\CarbonInterface;

final readonly class ActivityEntry
{
    /** @param  array<string, mixed>  $properties raw, filtered by the recorder */
    public function __construct(
        public int $companyId,
        public ?int $departmentId,
        public ?int $userId,
        public ActivityAction $action,
        public ActivitySubjectType $subjectType,
        public int $subjectId,
        public ?int $issueId,
        public array $properties,
        public CarbonInterface $createdAt,
    ) {}
}
