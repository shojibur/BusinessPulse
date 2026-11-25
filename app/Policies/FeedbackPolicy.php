<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\Outcome;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/** Feedback may be given by a reviewer (Owner, or Manager of the department) of the target's issue. */
class FeedbackPolicy
{
    use AuthorizesByDepartment;

    public function create(User $user, Issue|Outcome $target): bool
    {
        if (! $this->isActiveOwnerOrManager($user)) {
            return false;
        }

        $issue = $target instanceof Outcome ? $target->issue : $target;

        return Issue::query()->visibleTo($user)->whereKey($issue->id)->exists();
    }
}
