<?php

namespace App\Services\Meetings;

use App\Enums\DeadlineResolution;
use Carbon\CarbonImmutable;

final readonly class DeadlineResult
{
    public function __construct(public DeadlineResolution $resolution, public ?CarbonImmutable $date) {}
}
