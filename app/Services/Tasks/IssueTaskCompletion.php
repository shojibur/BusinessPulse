<?php

namespace App\Services\Tasks;

use Illuminate\Support\Facades\DB;

/** The monitoring condition, owned by S07: at least one completed and no open linked task. Cancelled tasks are ignored. */
final class IssueTaskCompletion
{
    public function isSatisfied(int $issueId): bool
    {
        $row = DB::table('tasks')->where('issue_id', $issueId)
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'completed') AS done, COUNT(*) FILTER (WHERE status IN ('pending','in_progress','blocked')) AS open")
            ->first();

        return (int) $row->done >= 1 && (int) $row->open === 0;
    }
}
