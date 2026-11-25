<?php

namespace App\Services\Tasks;

use App\Enums\TaskDraftStatus;
use App\Models\Meeting;
use App\Models\TaskDraft;
use App\Models\User;
use App\Services\Meetings\ExcerptVerifier;
use App\Services\Meetings\TextNormalizer;

/** The `draftReview` prop of the meeting page (Owner and Manager only). */
final class TaskDraftReviewProps
{
    public function __construct(private readonly ExcerptVerifier $excerpts, private readonly TextNormalizer $text) {}

    /**
     * @return array<string, mixed>|null
     */
    public function for(Meeting $meeting, User $user): ?array
    {
        if ($user->isStaff()) {
            return null;
        }

        $meeting->loadMissing(['issues:id,title', 'department']);
        $transcript = $meeting->transcript === null ? null : $this->text->nfc($meeting->transcript);
        $drafts = $meeting->taskDrafts()->with(['assignee:id,name', 'task:id'])->orderBy('id')->get();

        $present = fn (TaskDraft $d) => $this->present($d, $transcript);
        $pending = $drafts->where('status', TaskDraftStatus::Pending)->map($present)->values()->all();
        $history = $drafts->whereIn('status', [TaskDraftStatus::Approved, TaskDraftStatus::Rejected])
            ->sortByDesc(fn (TaskDraft $d) => $d->reviewed_at?->timestamp ?? 0)->map($present)->values()->all();

        $issueOptions = $meeting->issues->map(fn ($i) => ['id' => $i->id, 'title' => $i->title])->values()->all();

        return [
            'pending' => $pending,
            'history' => $history,
            'supersededCount' => $drafts->where('status', TaskDraftStatus::Superseded)->count(),
            'assigneeOptions' => AllowedAssignees::query($meeting->department_id)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
            'issueOptions' => $issueOptions,
            // A visible form default only when the meeting has exactly one linked issue; the server never fills it in.
            'defaultIssueId' => count($issueOptions) === 1 ? $issueOptions[0]['id'] : null,
            'minDueDate' => TaskDueDateRule::minimum($meeting->meeting_date)->format('Y-m-d'),
            'emptyReason' => $pending !== [] ? null : match (true) {
                $drafts->isEmpty() => 'never_extracted',
                $drafts->where('status', TaskDraftStatus::Approved)->isNotEmpty() => 'approved',
                default => 'rejected',
            },
            'transcriptChangedSinceExtraction' => $meeting->transcript_edited_at !== null
                && ($meeting->extracted_at === null || $meeting->transcript_edited_at->gt($meeting->extracted_at)),
            'canReExtract' => in_array($meeting->status->value, ['ready_for_review'], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(TaskDraft $draft, ?string $transcript): array
    {
        $blockers = [];
        trim((string) $draft->suggested_title) === '' && $blockers[] = 'title';
        $draft->assignee_id === null && $blockers[] = 'assignee_id';
        $draft->due_date === null && $blockers[] = 'due_date';

        // Stale: the supporting quote is no longer in the CURRENT transcript. Approval is refused server-side too.
        $stale = $draft->status === TaskDraftStatus::Pending
            && ($transcript === null || $this->excerpts->verify($transcript, $draft->transcript_excerpt) === null);

        return [
            'id' => $draft->id,
            'status' => $draft->status->value,
            'title' => $draft->suggested_title,
            'description' => $draft->suggested_description,
            'assigneeId' => $draft->assignee_id,
            'assigneeName' => $draft->assignee?->name,
            'assigneeResolution' => $draft->assignee_resolution->value,
            'suggestedAssigneeName' => $draft->suggested_assignee_name,
            'dueDate' => $draft->due_date?->format('Y-m-d'),
            'deadlineResolution' => $draft->deadline_resolution->value,
            'suggestedDueDateText' => $draft->suggested_due_date_text,
            'issueId' => $draft->issue_id,
            'transcriptExcerpt' => $draft->transcript_excerpt,
            'rejectionReason' => $draft->status === TaskDraftStatus::Rejected ? $draft->rejection_reason : null,
            'taskId' => $draft->task_id,
            'taskUrl' => $draft->task_id === null ? null : route('tasks.show', $draft->task_id),
            'blockers' => $blockers,
            'stale' => $stale,
            'extractionRun' => $draft->extraction_run,
        ];
    }
}
