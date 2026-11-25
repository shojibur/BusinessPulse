<?php

namespace App\Models;

use App\Enums\FailureStage;
use App\Enums\MeetingStatus;
use App\Enums\TranscriptSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A meeting of one department. meetings.status is written only by MeetingStateMachine. The company is reached
 * through the department. recording_path is never exposed to the front end.
 *
 * @property int $id
 * @property int $department_id
 * @property int $created_by
 * @property string $title
 * @property Carbon $meeting_date
 * @property string|null $recording_path
 * @property string|null $recording_original_name
 * @property string|null $recording_mime
 * @property int|null $recording_size
 * @property Carbon|null $recording_uploaded_at
 * @property Carbon|null $recording_consent_at
 * @property int|null $recording_consent_by
 * @property Carbon|null $recording_deleted_at
 * @property string|null $transcript
 * @property TranscriptSource|null $transcript_source
 * @property Carbon|null $transcript_edited_at
 * @property list<array{summary: string, excerpt: string}>|null $decisions
 * @property MeetingStatus $status
 * @property FailureStage|null $failure_stage
 * @property string|null $error_message
 * @property int $extraction_discarded_count
 * @property Carbon|null $extracted_at
 */
class Meeting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date:Y-m-d',
            'recording_uploaded_at' => 'datetime',
            'recording_consent_at' => 'datetime',
            'recording_deleted_at' => 'datetime',
            'transcript_source' => TranscriptSource::class,
            'transcript_edited_at' => 'datetime',
            'decisions' => 'array',
            'status' => MeetingStatus::class,
            'failure_stage' => FailureStage::class,
            'extracted_at' => 'datetime',
        ];
    }

    /**
     * Owner: meetings of the company's departments. Manager: own department. Staff and everyone else: nothing.
     *
     * @param  Builder<Meeting>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('1 = 0');
        } elseif ($user->isOwner()) {
            $query->whereIn('meetings.department_id', Department::query()->where('company_id', $user->company_id)->select('id'));
        } elseif ($user->isManager() && $user->department_id !== null) {
            $query->where('meetings.department_id', $user->department_id);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * Staff get 403 on every meeting route before any scoping (they must not learn which ids exist); an out-of-scope
     * meeting is 404 for an Owner or Manager.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();

        abort_if($user === null, 404);
        abort_if($user->isStaff() || ! $user->is_active, 403);

        return static::query()->visibleTo($user)->whereKey($value)->firstOrFail();
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function consentGivenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recording_consent_by');
    }

    /** @return BelongsToMany<Issue, $this> */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'meeting_issues');
    }

    /** @return HasMany<TaskDraft, $this> */
    public function taskDrafts(): HasMany
    {
        return $this->hasMany(TaskDraft::class);
    }
}
