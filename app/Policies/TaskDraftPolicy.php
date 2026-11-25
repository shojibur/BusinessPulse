<?php

namespace App\Policies;

use App\Models\TaskDraft;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/** Reviewing drafts is for the Owner and the Manager of the meeting's department. Staff never. */
class TaskDraftPolicy
{
    use AuthorizesByDepartment;

    public function view(User $user, TaskDraft $draft): bool
    {
        return $this->canReview($user, $draft);
    }

    public function update(User $user, TaskDraft $draft): bool
    {
        return $this->canReview($user, $draft);
    }

    public function approve(User $user, TaskDraft $draft): bool
    {
        return $this->canReview($user, $draft);
    }

    public function reject(User $user, TaskDraft $draft): bool
    {
        return $this->canReview($user, $draft);
    }

    private function canReview(User $user, TaskDraft $draft): bool
    {
        $meeting = $draft->meeting()->with('department')->first();

        if ($meeting === null) {
            return false;
        }

        if ($this->isActiveOwner($user)) {
            return $meeting->department?->company_id === $user->company_id;
        }

        return $user->is_active && $user->isManager() && $this->sameDepartment($user, $meeting->department_id);
    }
}
