<?php

require_once __DIR__.'/helpers.php';

use App\Enums\ActivityAction;
use App\Enums\ActivitySubjectType;
use App\Events\Intelligence\EvaluationRunCompleted;
use App\Events\Meetings\ExtractionCompleted;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityEntry;
use App\Services\Activity\ActivityMap;
use App\Services\Activity\ActivityRecorder;
use App\Services\Activity\PropertyAllowList;
use App\Services\Activity\TraceTimelineBuilder;
use App\Services\Outcomes\IssueOutcomeProps;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('the recorder keeps only allow-listed scalar properties and still writes the row', function () {
    $x = monitoringScenario();
    app(ActivityRecorder::class)->record(new ActivityEntry(
        $x->department->company_id, $x->department->id, $x->owner->id, ActivityAction::TaskStatusChanged, ActivitySubjectType::Task, 1, null,
        ['from' => 'pending', 'to' => 'completed', 'transcript' => 'Priya said RM 124,000 was missed', 'comment' => 'secret', 'long' => str_repeat('a', 101), 'nested' => ['a' => 1]],
        now(),
    ));

    $row = ActivityLog::latest('id')->first();
    expect($row->properties)->toEqual(['from' => 'pending', 'to' => 'completed'])->and(json_encode($row->properties))->not->toContain('RM 124,000');
    $filtered = app(PropertyAllowList::class)->filter(ActivityAction::BriefFailed, ['errorCode' => str_repeat('x', 101), 'other' => 1]);
    expect($filtered)->toBe([]);
    expect(fn () => $row->update(['action' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $row->delete())->toThrow(LogicException::class);
});

test('the core events of S03 to S08 are recorded with the approved properties and no free text', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    importFixture($x->owner, 'week10-recovery', '2026-10-12 09:00');

    $actions = ActivityLog::pluck('action')->unique()->all();
    expect($actions)->toContain('import.completed', 'issue.created', 'issue.status_changed', 'outcome.evaluated')
        ->and(ActivityLog::where('action', 'rule_evaluation.evaluated')->count())->toBe(0); // the Polish listener is not registered

    $outcome = ActivityLog::where('action', 'outcome.evaluated')->orderByDesc('id')->first();
    expect($outcome->properties)->toEqual(['result' => 'improved', 'phase' => 'post_action', 'comparable' => true, 'resolutionConditionMet' => true, 'lifecycleAction' => 'resolved'])
        ->and($outcome->subject_type)->toBe('Outcome')->and($outcome->issue_id)->not->toBeNull()->and($outcome->user_id)->toBeNull();
    $status = ActivityLog::where('action', 'issue.status_changed')->get()->last();
    expect(array_keys($status->properties))->toEqualCanonicalizing(['from', 'to', 'hasReason']);
    foreach (ActivityLog::all() as $row) {
        expect(json_encode($row->properties))->not->toContain('Priya')->not->toContain('transcript');
        expect(collect($row->properties)->filter(fn ($v) => is_string($v) && mb_strlen($v) > 100))->toBeEmpty();
    }
});

test('a meeting event writes one row per linked issue, or one row when none is linked; evaluation runs write none', function () {
    $x = monitoringScenario();
    $meeting = Meeting::create(['department_id' => $x->department->id, 'created_by' => $x->owner->id, 'title' => 'Huddle', 'meeting_date' => '2026-09-28']);
    $before = ActivityLog::count();

    event(new ExtractionCompleted($meeting, 1, 2, 4, $x->owner->id));
    expect(ActivityLog::count() - $before)->toBe(1)->and(ActivityLog::latest('id')->first()->issue_id)->toBeNull();

    $meeting->issues()->attach([$x->decline->id, $x->target->id]);
    $before = ActivityLog::count();
    event(new ExtractionCompleted($meeting, 2, 1, 3, $x->owner->id));
    expect(ActivityLog::count() - $before)->toBe(2)->and(ActivityLog::latest('id')->take(2)->pluck('issue_id')->sort()->values()->all())->toBe([$x->decline->id, $x->target->id]);

    event(new EvaluationRunCompleted($x->department->id, null, null, [1]));
    // A run itself writes no activity row (only the outcomes it verifies do): no evaluation-run action exists.
    expect(ActivityLog::where('action', 'like', 'rule_evaluation.%')->count())->toBe(0)
        ->and(collect(ActivityMap::core())->pluck('action')->map->value->contains('evaluation_run.completed'))->toBeFalse();
});

test('a failing activity listener never fails or rolls back the business action', function () {
    $x = monitoringScenario();
    config(['queue.default' => 'database']);
    app()->bind(ActivityRecorder::class, fn () => new class(new PropertyAllowList) extends ActivityRecorder
    {
        public function record(ActivityEntry $entry): void
        {
            throw new RuntimeException('activity store down');
        }
    });
    $task = Task::query()->where('issue_id', $x->decline->id)->first();
    $task->forceFill(['status' => 'pending', 'completed_at' => null])->save();
    $priya = User::where('email', 'priya@meridian.demo')->first();

    $this->actingAs($priya)->post("/tasks/{$task->id}/status", ['status' => 'completed'])->assertRedirect();
    expect($task->fresh()->status->value)->toBe('completed'); // the business action is committed and reported as a success

    $queued = DB::table('jobs')->where('payload', 'like', '%RecordActivity%')->count();
    expect($queued)->toBeGreaterThan(0);
    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1, '--queue' => 'default', '--sleep' => 0]);

    // The failing listener is retried by the queue (released with backoff, attempts counted); the action stays committed.
    expect(DB::table('jobs')->where('payload', 'like', '%RecordActivity%')->where('attempts', '>=', 1)->count())->toBeGreaterThan(0)->and($task->fresh()->status->value)->toBe('completed');
});

test('the trace runs from imported data to the outcome using persisted records, and says when a stage is empty', function () {
    $x = monitoringScenario();
    importFixture($x->owner, 'week9-interim', '2026-10-05 09:00');
    importFixture($x->owner, 'week10-recovery', '2026-10-12 09:00');
    $trace = app(TraceTimelineBuilder::class)->build($x->decline->fresh(), $x->owner);

    expect(collect($trace['stages'])->pluck('stage')->all())->toBe(['business_data', 'rule_evaluation', 'issue', 'brief', 'meeting', 'task', 'outcome']);
    $by = collect($trace['stages'])->keyBy('stage');
    expect($by['business_data']['empty'])->toBeFalse()->and($by['rule_evaluation']['empty'])->toBeFalse()->and($by['issue']['entries'])->not->toBeEmpty()
        ->and($by['brief']['empty'])->toBeTrue()->and($by['meeting']['empty'])->toBeTrue()->and($by['outcome']['entries'])->toHaveCount(2)
        ->and(collect($by['outcome']['entries'])->pluck('phaseLabel')->all())->toBe(['During action — not used for verification', 'After action — used for verification'])
        ->and(collect($by['issue']['entries'])->pluck('label')->implode(' | '))->toContain('Issue created')->toContain('Status changed from monitoring to resolved');

    // Entries are ordered by time inside a stage and nothing from the other issue leaks in.
    $times = collect($by['issue']['entries'])->pluck('at')->all();
    expect($times)->toBe(collect($times)->sort()->values()->all());
    foreach ($trace['stages'] as $stage) {
        foreach ($stage['entries'] as $entry) {
            expect($entry['subject']['type'] === 'Issue' ? $entry['subject']['id'] : $x->decline->id)->toBe($x->decline->id);
        }
    }
    expect(app(TraceTimelineBuilder::class)->build($x->decline->fresh(), User::where('email', 'priya@meridian.demo')->first()))->toBeNull();
});

test('the trace never invents events for work done before recording: record entries come from linked rows only', function () {
    $x = monitoringScenario();
    ActivityLog::query()->delete(); // simulate history that predates activity recording (test-only raw cleanup)
    $trace = app(TraceTimelineBuilder::class)->build($x->decline->fresh(), $x->owner);
    $by = collect($trace['stages'])->keyBy('stage');

    expect($by['issue']['entries'])->toBeEmpty()->and($by['issue']['empty'])->toBeTrue()                       // no activity rows, so no invented issue events
        ->and(collect($by['business_data']['entries'])->pluck('kind')->unique()->all())->toBe(['record'])   // imports exist as records
        ->and(collect($by['rule_evaluation']['entries'])->pluck('kind')->unique()->all())->toBe(['record'])
        ->and(collect($by['task']['entries'])->pluck('kind')->unique()->all())->toBe(['record']);
});

test('management activity is not exposed to staff through the issue props', function () {
    $x = monitoringScenario();
    $staff = User::where('email', 'priya@meridian.demo')->first();
    $this->actingAs($staff)->get("/issues/{$x->decline->id}")->assertForbidden();
    expect(app(IssueOutcomeProps::class)->outcomes($x->decline->fresh(), $staff))->toBeNull()
        ->and(app(IssueOutcomeProps::class)->feedback($x->decline->fresh(), $staff))->toBeNull();
});
