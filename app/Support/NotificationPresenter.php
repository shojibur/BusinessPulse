<?php

namespace App\Support;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Turns stored notifications into display data. A notification about an issue the user can no longer access
 * is shown without a link.
 */
class NotificationPresenter
{
    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return list<array<string, mixed>>
     */
    public static function many(User $user, Collection $notifications): array
    {
        $issueIds = $notifications->map(fn ($n) => $n->data['issue_id'] ?? null)->filter()->unique()->values();
        $accessible = $issueIds->isEmpty()
            ? collect()
            // In scope AND allowed by the policy (Staff are in the department scope but may not open issues).
            : Issue::visibleTo($user)->whereIn('id', $issueIds)->get()
                ->filter(fn (Issue $issue) => Gate::forUser($user)->allows('view', $issue))
                ->pluck('id')->flip();

        return $notifications->map(function (DatabaseNotification $notification) use ($accessible): array {
            $data = $notification->data;
            $issueId = $data['issue_id'] ?? null;

            return [
                'id' => $notification->id,
                'title' => (string) ($data['title'] ?? 'Notification'),
                'severity' => $data['severity'] ?? null,
                'summary' => $data['summary_line'] ?? null,
                'department' => $data['department_name'] ?? null,
                'url' => $issueId !== null && $accessible->has($issueId) ? ($data['url'] ?? null) : null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
