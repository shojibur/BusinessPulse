<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Owner: tasks of the company. Manager: tasks of the own department. Staff: only tasks assigned to them, and they may
 * not cancel a task or reopen a completed one. Route binding already hides out-of-scope tasks (404).
 */
class TaskPolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->inScope($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->inScope($user, $task);
    }

    public function changeStatus(User $user, Task $task, TaskStatus $to): bool
    {
        if (! $this->inScope($user, $task)) {
            return false;
        }

        if ($this->managesTask($user, $task)) {
            return true;
        }

        // The assigned employee (Staff): progress the task, but never cancel it or reopen a completed one.
        return $to !== TaskStatus::Cancelled && $task->status !== TaskStatus::Completed;
    }

    private function managesTask(User $user, Task $task): bool
    {
        if ($this->isActiveOwner($user)) {
            return $user->company_id === $task->department?->company_id;
        }

        return $user->is_active && $user->isManager() && $this->sameDepartment($user, $task->department_id);
    }

    private function inScope(User $user, Task $task): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $this->managesTask($user, $task) || ($user->isStaff() && $task->assignee_id === $user->id);
    }
}
