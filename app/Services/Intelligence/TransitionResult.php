<?php

namespace App\Services\Intelligence;

use App\Models\Issue;

final readonly class TransitionResult
{
    public function __construct(public Issue $issue, public bool $changed) {}
}
