<?php

namespace App\Services\Meetings;

use App\Enums\AssigneeResolution;

final readonly class AssigneeResult
{
    public function __construct(public AssigneeResolution $resolution, public ?int $userId) {}
}
