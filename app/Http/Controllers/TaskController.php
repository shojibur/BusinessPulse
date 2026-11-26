<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\TaskIndexRequest;
use App\Models\Issue;
use App\Models\Task;
use App\Models\TaskDraft;
use App\Models\User;
use App\Services\Tasks\OverdueCondition;
use App\Services\Tasks\TaskCounts;
use App\Services\Tasks\TaskPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(private readonly TaskPresenter $presenter, private readonly TaskCounts $counts) {}

    /** Columns of the board, in order. Cancelled work is counted in a footnote, not shown as a column. */
    private const BOARD_STATUSES = [TaskStatus::Pending, TaskStatus::InProgress, TaskStatus::Blocked, TaskStatus::Completed];

    /** The most rows the board loads in one view. */
    private const BOARD_LIMIT = 100;

    /** Management page: Owner (company) and Manager (department). Staff get 403. Board by default, table with `view=list`. */
    public function index(TaskIndexRequest $request): Response
    {
        $user = $request->user();
        $today = OverdueCondition::today();
        $view = $request->query('view') === 'list' ? 'list' : 'board';
        $issueFilter = $request->filled('issue') && ctype_digit((string) $request->query('issue')) ? (int) $request->query('issue') : null;
        $search = trim((string) $request->query('q', ''));
        $search = $search === '' ? null : mb_substr($search, 0, 100);

        // The filtered scope, without ordering or selects, so counts and rows always agree.
        $filtered = fn () => Task::query()->visibleTo($user)
            ->when($request->openOnly(), fn ($q) => $q->whereIn('tasks.status', OverdueCondition::OPEN))
            ->when($request->status() !== null, fn ($q) => $q->where('tasks.status', $request->status()->value))
            ->when($request->overdue(), fn ($q) => OverdueCondition::apply($q, $today))
            // Out-of-scope assignee values are simply dropped: the visibility scope above already limits the rows.
            ->when($request->assignee() !== null, fn ($q) => $q->where('tasks.assignee_id', $request->assignee()))
            ->when($issueFilter !== null, fn ($q) => $q->where('tasks.issue_id', $issueFilter))
            ->when($search !== null, fn ($q) => $q->where('tasks.title', 'ilike', '%'.addcslashes($search, '%_\\').'%'));

        $rows = fn ($query) => $query->with(['assignee:id,name', 'department:id,name', 'issue:id,title'])
            ->selectRaw('tasks.*, '.OverdueCondition::sql().' as is_overdue', [$today->toDateString()])
            ->orderByDesc('is_overdue')->orderBy('tasks.due_date')->orderByDesc('tasks.id');

        $mapRow = fn (Task $task) => $this->presenter->row($task, $user, $today) + ['description' => $this->oneLine((string) $task->description)];

        $tasks = null;
        $pagination = null;
        $board = null;

        if ($view === 'list') {
            $page = $rows($filtered())->paginate(15)->withQueryString();
            $tasks = $page->getCollection()->map($mapRow)->values();
            $pagination = [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
                'prev_url' => $page->previousPageUrl(), 'next_url' => $page->nextPageUrl(),
            ];
        } else {
            $counts = $filtered()->reorder()->selectRaw('tasks.status, count(*) as total')->groupBy('tasks.status')->pluck('total', 'status');
            $loaded = $rows($filtered())->whereIn('tasks.status', array_map(fn (TaskStatus $s) => $s->value, self::BOARD_STATUSES))->limit(self::BOARD_LIMIT)->get();
            $columns = array_map(fn (TaskStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
                'items' => $loaded->where('status', $status)->map($mapRow)->values()->all(),
            ], self::BOARD_STATUSES);
            $board = [
                'columns' => $columns,
                'shown' => $loaded->count(),
                'total' => array_sum(array_column($columns, 'count')),
                'cancelled' => (int) ($counts[TaskStatus::Cancelled->value] ?? 0),
            ];
        }

        $assigneeIds = Task::query()->visibleTo($user)->distinct()->pluck('assignee_id');
        $summary = $user->isOwner() ? $this->counts->forCompany($user->company_id) : $this->counts->forDepartment((int) $user->department_id);
        $byStatus = Task::query()->visibleTo($user)->reorder()->selectRaw('tasks.status, count(*) as total')->groupBy('tasks.status')->pluck('total', 'status');

        return Inertia::render('tasks/Index', [
            'view' => $view,
            'tasks' => $tasks,
            'pagination' => $pagination,
            'board' => $board,
            'filters' => [
                'status' => $request->openOnly() ? 'open' : $request->status()?->value,
                'assignee' => $request->assignee(),
                'overdue' => $request->overdue(),
                'issue' => $issueFilter,
                'q' => $search,
            ],
            'options' => [
                'assignees' => User::query()->whereIn('id', $assigneeIds)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'statuses' => array_map(fn (TaskStatus $s) => ['value' => $s->value, 'label' => $s->label()], TaskStatus::cases()),
                'issues' => Issue::query()->visibleTo($user)->whereIn('id', Task::query()->visibleTo($user)->whereNotNull('tasks.issue_id')->select('tasks.issue_id'))
                    ->orderBy('title')->get(['id', 'title'])->map(fn ($i) => ['id' => $i->id, 'title' => $i->title])->values(),
            ],
            'summary' => $summary,
            'stats' => [
                'total' => (int) $byStatus->sum(),
                'inProgress' => (int) ($byStatus[TaskStatus::InProgress->value] ?? 0),
                'completed' => (int) ($byStatus[TaskStatus::Completed->value] ?? 0),
                'overdue' => $summary['overdue'],
                'pendingDrafts' => TaskDraft::query()->reviewable()->whereHas('meeting', fn ($q) => $q->visibleTo($user))->count(),
            ],
            'businessToday' => $today->format('Y-m-d'),
        ]);
    }

    /** One short line of text for a table cell. */
    private function oneLine(string $text): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $text)), 90);
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
