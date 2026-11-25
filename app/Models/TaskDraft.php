<?php

namespace App\Models;

use App\Enums\AssigneeResolution;
use App\Enums\DeadlineResolution;
use App\Enums\TaskDraftStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A proposed task extracted from a meeting. S06 only creates `pending` drafts and retires them as `superseded`;
 * approval, assignment and task creation belong to S07. A draft is never an assigned task.
 *
 * @property int $id
 * @property int $meeting_id
 * @property int|null $issue_id
 * @property string $suggested_title
 * @property string $suggested_description
 * @property string|null $suggested_assignee_name
 * @property int|null $assignee_id
 * @property AssigneeResolution $assignee_resolution
 * @property string|null $suggested_due_date_text
 * @property Carbon|null $suggested_due_date
 * @property Carbon|null $due_date
 * @property DeadlineResolution $deadline_resolution
 * @property string $transcript_excerpt
 * @property TaskDraftStatus $status
 * @property int|null $task_id
 * @property string|null $rejection_reason
 * @property int $extraction_run
 */
class TaskDraft extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assignee_resolution' => AssigneeResolution::class,
            'deadline_resolution' => DeadlineResolution::class,
            'status' => TaskDraftStatus::class,
            'suggested_due_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
        ];
    }

    /** @param  Builder<TaskDraft>  $query */
    public function scopeReviewable(Builder $query): void
    {
        $query->where('status', TaskDraftStatus::Pending->value);
    }

    /**
     * Staff get 403 before any scoping (as for meetings); Owner (company) and Manager (department) scope through the
     * meeting; anything else is 404.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();

        abort_if($user === null, 404);
        abort_if($user->isStaff() || ! $user->is_active, 403);

        return static::query()
            ->whereHas('meeting', fn ($query) => $query->visibleTo($user))
            ->whereKey($value)
            ->firstOrFail();
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
