<?php

namespace App\Actions\Tasks;

use App\Models\Task;

final readonly class ApprovalResult
{
    public function __construct(public Task $task, public bool $created) {}
}
