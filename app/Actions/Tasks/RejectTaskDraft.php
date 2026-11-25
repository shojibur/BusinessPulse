<?php

namespace App\Actions\Tasks;

use App\Enums\TaskDraftStatus;
use App\Models\TaskDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Rejects a pending draft; the row and its history are kept. A repeat rejection is a no-op success. */
final class RejectTaskDraft
{
    public function handle(TaskDraft $routeDraft, User $actor, ?string $reason): TaskDraft
    {
        return DB::transaction(function () use ($routeDraft, $actor, $reason): TaskDraft {
            $draft = TaskDraft::query()->whereKey($routeDraft->getKey())->lockForUpdate()->firstOrFail();

            if ($draft->status === TaskDraftStatus::Rejected) {
                return $draft;
            }

            if ($draft->status !== TaskDraftStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'This draft can no longer be rejected.']);
            }

            $reason = $reason === null ? null : trim($reason);

            $draft->forceFill([
                'status' => TaskDraftStatus::Rejected,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason === '' ? null : $reason,
            ])->save();

            return $draft;
        });
    }
}
