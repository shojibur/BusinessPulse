<?php

namespace App\Actions\Tasks;

use App\Enums\TaskDraftStatus;
use App\Models\Meeting;
use App\Models\TaskDraft;
use App\Services\Tasks\DraftEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Edits the reviewable fields of a PENDING draft under a row lock (serialized with approval). */
final class UpdateTaskDraft
{
    public function __construct(private readonly DraftEditor $editor) {}

    /**
     * @param  array<string, mixed>  $edits
     */
    public function handle(TaskDraft $routeDraft, array $edits): TaskDraft
    {
        return DB::transaction(function () use ($routeDraft, $edits): TaskDraft {
            $draft = TaskDraft::query()->whereKey($routeDraft->getKey())->lockForUpdate()->firstOrFail();

            if ($draft->status !== TaskDraftStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Only a pending draft can be edited.']);
            }

            $meeting = Meeting::query()->findOrFail($draft->meeting_id);
            $draft->forceFill($this->editor->apply($draft, $meeting, $edits)->draftColumns())->save();

            return $draft;
        });
    }
}
