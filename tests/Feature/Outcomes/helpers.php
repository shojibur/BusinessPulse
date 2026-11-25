<?php

use App\Models\Department;
use App\Models\Issue;
use App\Models\Outcome;
use App\Models\Task;
use App\Models\User;
use App\Services\Intelligence\IssueLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * The demo timeline up to the monitoring phase, through the REAL import, evaluation and lifecycle paths: history (21 Sep),
 * week 8 decline (28 Sep, high decline issue and medium target issue), action started on 28 Sep, one completed linked task,
 * monitoring from 30 Sep. Nothing about the outcome is pre-computed.
 *
 * @return object{owner: User, department: Department, decline: Issue, target: Issue}
 */
function monitoringScenario(): stdClass
{
    $x = new stdClass;
    $x->owner = demoOwner();
    $x->department = demoDepartment();

    importFixture($x->owner, 'history', '2026-09-21 09:00');
    importFixture($x->owner, 'week8-decline', '2026-09-28 09:00');

    $x->decline = Issue::query()->whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->firstOrFail();
    $x->target = Issue::query()->whereHas('rule', fn ($q) => $q->where('key', 'sales_target_shortfall'))->firstOrFail();

    foreach ([$x->decline, $x->target] as $issue) {
        app(IssueLifecycle::class)->startAction($issue->fresh(), null);
        $task = new Task(['department_id' => $x->department->id, 'issue_id' => $issue->id, 'title' => 'Recovery action', 'description' => '', 'assignee_id' => User::where('email', 'priya@meridian.demo')->value('id'),
            'assigned_by' => $x->owner->id, 'due_date' => '2026-10-02']);
        $task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
    }

    setClock('2026-09-30 09:00');

    foreach ([$x->decline, $x->target] as $issue) {
        app(IssueLifecycle::class)->startMonitoring($issue->fresh(), null);
    }

    $x->decline->refresh();
    $x->target->refresh();

    return $x;
}

/** CSV rows (6 trading days x 3 branches) for a week whose revenue totals exactly $total (the last row takes the remainder). */
function weekRowsForTotal(string $monday, string $total, string $target = '8666.67'): string
{
    $each = bcdiv($total, '18', 2);
    $last = bcsub($total, bcmul($each, '17', 2), 2);
    $rows = '';
    $i = 0;

    for ($day = 0; $day < 6; $day++) {
        $date = CarbonImmutable::parse($monday, 'UTC')->addDays($day)->format('Y-m-d');

        foreach (['KL', 'PG', 'JB'] as $branch) {
            $i++;
            $rows .= "{$date},{$branch},".($i === 18 ? $last : $each).",{$target},\n";
        }
    }

    return $rows;
}

function latestOutcome(Issue $issue): ?Outcome
{
    return Outcome::query()->where('issue_id', $issue->id)->orderByDesc('evaluated_at')->orderByDesc('id')->first();
}

/** Asserts the database refuses a statement, inside a savepoint so the test transaction stays usable. */
function dbRejects(callable $statement): void
{
    $rejected = false;

    try {
        DB::transaction($statement);
    } catch (QueryException) {
        $rejected = true;
    }

    expect($rejected)->toBeTrue();
}
