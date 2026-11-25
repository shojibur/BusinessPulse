<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByDepartment;

/**
 * Meetings belong to a department. Owner: every meeting of the company. Manager: own department. Staff: never (they
 * must not reach confidential management recordings or transcripts). Deleting a recording is Owner only.
 */
class MeetingPolicy
{
    use AuthorizesByDepartment;

    public function viewAny(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function create(User $user): bool
    {
        return $this->isActiveOwnerOrManager($user);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function uploadRecording(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function enterTranscript(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function editTranscript(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function retry(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function reExtract(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function stream(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function download(User $user, Meeting $meeting): bool
    {
        return $this->inScope($user, $meeting);
    }

    public function deleteRecording(User $user, Meeting $meeting): bool
    {
        return $this->isActiveOwner($user) && $this->inScope($user, $meeting);
    }

    private function inScope(User $user, Meeting $meeting): bool
    {
        $departmentCompany = $meeting->department?->company_id;

        if ($this->isActiveOwner($user)) {
            return $departmentCompany === $user->company_id;
        }

        return $user->is_active && $user->isManager() && $this->sameDepartment($user, $meeting->department_id);
    }
}
