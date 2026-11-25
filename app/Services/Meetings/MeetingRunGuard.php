<?php

namespace App\Services\Meetings;

use App\Enums\MeetingErrorCode;
use App\Enums\TranscriptSource;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Run-time authorization re-checks (R8, D27). Both jobs call this AFTER claiming the meeting and BEFORE any provider
 * call, because consent, the recording or the department may have changed since the request was accepted. A failed
 * check throws a MeetingStageException and nothing is sent to the provider.
 */
final class MeetingRunGuard
{
    /**
     * @return Meeting|null null when the meeting no longer exists
     *
     * @throws MeetingStageException
     */
    public function forTranscription(int $meetingId, ?int $actorUserId = null): ?Meeting
    {
        $meeting = Meeting::query()->with('department.company')->find($meetingId);

        if ($meeting === null) {
            return null;
        }

        if ($meeting->department === null) {
            throw new MeetingStageException(MeetingErrorCode::DepartmentUnavailable);
        }

        if ($meeting->recording_consent_at === null || $meeting->recording_consent_by === null) {
            throw new MeetingStageException(MeetingErrorCode::ConsentMissing);
        }

        if ($meeting->recording_deleted_at !== null || $meeting->recording_path === null) {
            throw new MeetingStageException(MeetingErrorCode::RecordingDeleted);
        }

        if (! Storage::disk('local')->exists($meeting->recording_path)) {
            throw new MeetingStageException(MeetingErrorCode::RecordingMissing);
        }

        // Both the requester and the user who gave the consent must STILL be authorized for this meeting.
        $this->assertInitiatorAuthorized($meeting, $actorUserId ?? $meeting->recording_consent_by);
        $this->assertInitiatorAuthorized($meeting, $meeting->recording_consent_by);

        return $meeting;
    }

    /**
     * @return Meeting|null null when the meeting no longer exists
     *
     * @throws MeetingStageException
     */
    public function forExtraction(int $meetingId, ?int $actorUserId = null): ?Meeting
    {
        $meeting = Meeting::query()->with('department.company')->find($meetingId);

        if ($meeting === null) {
            return null;
        }

        if ($meeting->department === null) {
            throw new MeetingStageException(MeetingErrorCode::DepartmentUnavailable);
        }

        $this->assertInitiatorAuthorized($meeting, $actorUserId ?? $meeting->created_by);

        // The recording may legitimately be gone after retention deletion; consent only matters for audio transcripts.
        if ($meeting->transcript_source === TranscriptSource::Audio) {
            if ($meeting->recording_consent_at === null) {
                throw new MeetingStageException(MeetingErrorCode::ConsentMissing);
            }

            $this->assertInitiatorAuthorized($meeting, $meeting->recording_consent_by);
        }

        return $meeting;
    }

    /**
     * Active staff and managers of the meeting's department, built at run time. The Owner is never in the list.
     *
     * @return Collection<int, User>
     */
    public function allowedStaff(Meeting $meeting): Collection
    {
        return User::query()
            ->where('department_id', $meeting->department_id)
            ->where('is_active', true)
            ->whereIn('role', ['staff', 'manager'])
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /**
     * The user who requested the processing (or gave the recording consent) must STILL be an active Owner of the
     * meeting's company or an active Manager of its department. A demoted, deactivated or moved user cannot cause audio
     * or transcript text to be sent to the provider after the fact. No new authorization infrastructure: the same roles
     * the policies use.
     *
     * @throws MeetingStageException
     */
    private function assertInitiatorAuthorized(Meeting $meeting, ?int $userId): void
    {
        $user = $userId === null ? null : User::query()->find($userId);

        $authorized = $user !== null && $user->is_active && (
            ($user->isOwner() && $user->company_id === $meeting->department->company_id)
            || ($user->isManager() && $user->department_id === $meeting->department_id)
        );

        if (! $authorized) {
            throw new MeetingStageException(MeetingErrorCode::ActorUnauthorized);
        }
    }
}
