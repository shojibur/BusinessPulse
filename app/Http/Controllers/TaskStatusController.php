<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\ChangeTaskStatus;
use App\Http\Requests\ChangeTaskStatusRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class TaskStatusController extends Controller
{
    public function __invoke(ChangeTaskStatusRequest $request, Task $task, ChangeTaskStatus $action): RedirectResponse
    {
        $action->handle($task, $request->target(), $request->user());

        return back();
    }
}
