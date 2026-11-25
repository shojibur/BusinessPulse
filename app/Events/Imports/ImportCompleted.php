<?php

namespace App\Events\Imports;

use App\Enums\ImportStatus;
use Carbon\CarbonImmutable;

/**
 * An import reached completed or completed_with_errors. S04 listens to start rule evaluation; S08 to log activity.
 */
final class ImportCompleted extends ImportEvent
{
    public function __construct(
        int $importId,
        int $departmentId,
        public readonly ImportStatus $status,
        public readonly int $insertedRows,
        public readonly int $correctedRows,
        public readonly int $unchangedRows,
        public readonly int $invalidRows,
        public readonly ?CarbonImmutable $minDate,
        public readonly ?CarbonImmutable $maxDate,
        ?int $actorUserId = null,
    ) {
        parent::__construct($importId, $departmentId, $actorUserId);
    }
}
