<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An accountable task. It exists only because a person approved a draft (or created it by hand). `status` and
 * `completed_at` are deliberately NOT fillable: TaskStatusMachine is the only writer of status, and the initial
 * status is set with forceFill.
 *
 * @property int $id
 * @property int $department_id
 * @property int|null $task_draft_id
 * @property int|null $meeting_id
 * @property int|null $issue_id
 * @property string $title
 * @property string $description
 * @property int $assignee_id
 * @property int $assigned_by
 * @property Carbon $due_date
 * @property TaskStatus $status
 * @property Carbon|null $completed_at
 */
class Task extends Model
{
    protected $fillable = ['department_id', 'task_draft_id', 'meeting_id', 'issue_id', 'title', 'description', 'assignee_id', 'assigned_by', 'due_date'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'status' => TaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Owner: tasks of the company's departments. Manager: own department. Staff: only tasks assigned to them.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('1 = 0');
        } elseif ($user->isOwner()) {
            $query->whereIn('tasks.department_id', Department::query()->where('company_id', $user->company_id)->select('id'));
        } elseif ($user->isManager() && $user->department_id !== null) {
            $query->where('tasks.department_id', $user->department_id);
        } elseif ($user->isStaff()) {
            $query->where('tasks.assignee_id', $user->id);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    /** A foreign or missing id gives the same 404. */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();

        abort_if($user === null, 404);

        return static::query()->visibleTo($user)->whereKey($value)->firstOrFail();
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<Issue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /** @return BelongsTo<TaskDraft, $this> */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(TaskDraft::class, 'task_draft_id');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at')->orderBy('id');
    }
}
