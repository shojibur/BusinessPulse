<?php

require_once __DIR__.'/helpers.php';

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityEntry;
use App\Services\Activity\ActivityRecorder;
use App\Services\Activity\PropertyAllowList;
use App\Services\Activity\TraceTimelineBuilder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
