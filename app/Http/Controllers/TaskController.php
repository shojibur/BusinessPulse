<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\TaskIndexRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\OverdueCondition;
use App\Services\Tasks\TaskCounts;
use App\Services\Tasks\TaskPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(private readonly TaskPresenter $presenter, private readonly TaskCounts $counts) {}

    /** Management list: Owner (company) and Manager (department). Staff get 403. */
    public function index(TaskIndexRequest $request): Response
    {
        $user = $request->user();
        $today = OverdueCondition::today();

        $query = Task::query()->visibleTo($user)->with(['assignee:id,name', 'department:id,name', 'issue:id,title'])
            ->selectRaw('tasks.*, '.OverdueCondition::sql().' as is_overdue', [$today->toDateString()])
            ->when($request->openOnly(), fn ($q) => $q->whereIn('tasks.status', OverdueCondition::OPEN))
            ->when($request->status() !== null, fn ($q) => $q->where('tasks.status', $request->status()->value))
            ->when($request->overdue(), fn ($q) => OverdueCondition::apply($q, $today))
            // Out-of-scope assignee values are simply dropped: the visibility scope above already limits the rows.
            ->when($request->assignee() !== null, fn ($q) => $q->where('tasks.assignee_id', $request->assignee()))
            ->orderByDesc('is_overdue')->orderBy('tasks.due_date')->orderByDesc('tasks.id');

        $tasks = $query->paginate(15)->withQueryString();
        $assigneeIds = Task::query()->visibleTo($user)->distinct()->pluck('assignee_id');

        return Inertia::render('tasks/Index', [
            'tasks' => $tasks->getCollection()->map(fn (Task $task) => $this->presenter->row($task, $user, $today))->values(),
            'pagination' => [
                'current_page' => $tasks->currentPage(), 'last_page' => $tasks->lastPage(), 'total' => $tasks->total(),
                'prev_url' => $tasks->previousPageUrl(), 'next_url' => $tasks->nextPageUrl(),
            ],
            'filters' => [
                'status' => $request->openOnly() ? 'open' : $request->status()?->value,
                'assignee' => $request->assignee(),
                'overdue' => $request->overdue(),
            ],
            'options' => [
                'assignees' => User::query()->whereIn('id', $assigneeIds)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'statuses' => array_map(fn (TaskStatus $s) => ['value' => $s->value, 'label' => $s->label()], TaskStatus::cases()),
            ],
            'summary' => $user->isOwner() ? $this->counts->forCompany($user->company_id) : $this->counts->forDepartment((int) $user->department_id),
            'businessToday' => $today->format('Y-m-d'),
        ]);
    }

    /** My Tasks: the signed-in user's own assigned tasks (every role). */
    public function mine(Request $request): Response
    {
        $user = $request->user();
        $today = OverdueCondition::today();
        $status = TaskStatus::tryFrom((string) $request->query('status'));
        $showCancelled = filter_var($request->query('showCancelled'), FILTER_VALIDATE_BOOLEAN);

        $tasks = Task::query()->where('assignee_id', $user->id)->with(['assignee:id,name', 'department:id,name', 'issue:id,title'])
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->orderBy('due_date')->orderBy('id')->get();

        $rows = fn ($set) => $set->map(fn (Task $t) => $this->presenter->row($t, $user, $today))->values()->all();
        $overdue = $tasks->filter(fn (Task $t) => OverdueCondition::isOverdue($t, $today));

        return Inertia::render('tasks/Mine', [
            'sections' => [
                'overdue' => $rows($overdue),
                'open' => $rows($tasks->filter(fn (Task $t) => $t->status->isOpen() && ! OverdueCondition::isOverdue($t, $today))),
                'completed' => $rows($tasks->where('status', TaskStatus::Completed)),
                'cancelled' => $showCancelled ? $rows($tasks->where('status', TaskStatus::Cancelled)) : null,
            ],
            'filters' => ['status' => $status?->value, 'showCancelled' => $showCancelled],
            'summary' => $this->counts->forUser($user->id),
            'businessToday' => $today->format('Y-m-d'),
        ]);
    }

    public function show(Request $request, Task $task): Response
    {
        Gate::authorize('view', $task);

        return Inertia::render('tasks/Show', $this->presenter->detail($task, $request->user(), OverdueCondition::today()));
    }
}
