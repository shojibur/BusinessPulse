<?php

namespace App\Services\Imports;

use App\Enums\ImportRowResult;
use App\Models\SalesRecord;

final readonly class ClassifiedRow
{
    public function __construct(
        public ValidRow $row,
        public ImportRowResult $result,
        public ?SalesRecord $activeRecord,
    ) {}
}
