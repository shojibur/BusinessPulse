<?php

namespace App\Services\Meetings;

use App\Models\Issue;
use App\Models\Meeting;
use App\Models\User;

/** The `meetings` prop of the Issue detail page: in-scope meetings only, no transcript text, null for Staff. */
final class MeetingsForIssue
{
    /**
     * @return array{items: list<array<string, mixed>>, createUrl: string}|null
     */
    public function __invoke(Issue $issue, User $user): ?array
    {
        if ($user->isStaff()) {
            return null;
        }

        $meetings = Meeting::query()->visibleTo($user)
            ->whereHas('issues', fn ($query) => $query->where('issues.id', $issue->id))
            ->orderByDesc('meeting_date')->orderByDesc('id')->limit(10)->get();

        return [
            'items' => $meetings->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meetingDate' => $meeting->meeting_date->format('Y-m-d'),
                'status' => $meeting->status->value,
                'statusLabel' => $meeting->status->label(),
                'url' => route('meetings.show', $meeting),
            ])->all(),
            'createUrl' => route('meetings.create', ['issue' => $issue->id]),
        ];
    }
}
