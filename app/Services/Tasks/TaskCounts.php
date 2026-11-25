<?php

namespace App\Services\Tasks;

use App\Models\Department;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Open and overdue task counts: one aggregate query each, never loading task rows. The Task list footer, My Tasks and
 * the dashboard all call this, so their figures match by construction.
 */
final class TaskCounts
{
    /** @return array{open: int, overdue: int} */
    public function forDepartment(int $departmentId): array
    {
        return $this->count(fn ($q) => $q->where('department_id', $departmentId));
    }

    /** @return array{open: int, overdue: int} */
    public function forCompany(int $companyId): array
    {
        return $this->count(fn ($q) => $q->whereIn('department_id', Department::query()->where('company_id', $companyId)->select('id')));
    }

    /** @return array{open: int, overdue: int} */
    public function forUser(int $userId): array
    {
        return $this->count(fn ($q) => $q->where('assignee_id', $userId));
    }

    /**
     * @return array{open: int, overdue: int}
     */
    private function count(Closure $scope): array
    {
        $row = $scope(DB::table('tasks'))
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('pending','in_progress','blocked')) AS open, COUNT(*) FILTER (WHERE ".OverdueCondition::sql().') AS overdue', [OverdueCondition::today()->toDateString()])
            ->first();

        return ['open' => (int) $row->open, 'overdue' => (int) $row->overdue];
    }
}
