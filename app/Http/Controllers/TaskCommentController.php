<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\AddTaskComment;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    public function store(StoreTaskCommentRequest $request, Task $task, AddTaskComment $action): RedirectResponse
    {
        $action->handle($task, $request->user(), $request->string('body')->toString());

        return back();
    }
}
