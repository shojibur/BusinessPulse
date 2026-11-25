<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\ApproveTaskDraft;
use App\Actions\Tasks\RejectTaskDraft;
use App\Actions\Tasks\UpdateTaskDraft;
use App\Http\Requests\ApproveTaskDraftRequest;
use App\Http\Requests\RejectTaskDraftRequest;
use App\Http\Requests\UpdateTaskDraftRequest;
use App\Models\TaskDraft;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/** Human review of AI-proposed task drafts. Nothing here calls an AI provider or reads the transcript text. */
class TaskDraftController extends Controller
{
    public function update(UpdateTaskDraftRequest $request, TaskDraft $taskDraft, UpdateTaskDraft $action): RedirectResponse
    {
        $action->handle($taskDraft, $request->edits());

        Flash::success('Draft saved.');

        return to_route('meetings.show', $taskDraft->meeting_id);
    }

    public function approve(ApproveTaskDraftRequest $request, TaskDraft $taskDraft, ApproveTaskDraft $action): RedirectResponse
    {
        $result = $action->handle($taskDraft, $request->user(), $request->edits());

        Flash::success('Task created and assigned to '.$result->task->assignee->name.'.');

        return to_route('meetings.show', $taskDraft->meeting_id);
    }

    public function reject(RejectTaskDraftRequest $request, TaskDraft $taskDraft, RejectTaskDraft $action): RedirectResponse
    {
        $action->handle($taskDraft, $request->user(), $request->input('reason'));

        Flash::success('Draft rejected.');

        return to_route('meetings.show', $taskDraft->meeting_id);
    }
}
