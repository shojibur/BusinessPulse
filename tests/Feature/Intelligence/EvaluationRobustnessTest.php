<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Actions\Intelligence\SeedDefaultRules;
use App\Enums\ImportStatus;
use App\Events\Intelligence\EvaluationRunCompleted;
use App\Jobs\EvaluateRulesJob;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Models\SalesRecord;
use App\Services\Imports\ReportingCoverage;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->f = identity();
    $this->owner = $this->f->owner;
});

test('a failing listener does not fail a completed import, and Refresh intelligence recovers the evaluation', function () {
    setClock('2026-09-28 09:00');
    // The evaluation cannot be queued after the import (for example the queue backend is down).
    $this->app->bind(DispatchEvaluation::class, fn () => new class extends DispatchEvaluation
    {
        public function handle(int $departmentId, ?int $importId = null, ?int $actorUserId = null): void
        {
            throw new RuntimeException('queue unavailable');
        }
    });
    Log::spy();

    $import = importCsv($this->owner, weekCsv('2026-09-21', '1000.00'));

    // The import itself is intact and completed.
    expect($import->status)->toBe(ImportStatus::Completed)->and(SalesRecord::where('is_active', true)->count())->toBe(18)
        ->and(RuleEvaluation::count())->toBe(0)->and(Issue::count())->toBe(0);
    Log::shouldHaveReceived('error')->with('evaluation_dispatch_failed', Mockery::on(fn ($context) => $context['import_id'] === $import->id && $context['exception'] === RuntimeException::class));

    // The Issues page tells the Owner that the evaluation is older than the import ...
    $this->app->forgetInstance(DispatchEvaluation::class);
    $this->app->bind(DispatchEvaluation::class, fn () => new DispatchEvaluation);
    $issue = fakeIssue($this->f->sales);
    $this->actingAs($this->owner)->get("/issues/{$issue->id}")->assertInertia(fn ($page) => $page->where('stale', true)->where('lastEvaluatedAt', null));

    // ... and Refresh intelligence is the recovery path.
    $this->actingAs($this->owner)->post('/issues/refresh')->assertRedirect();
    expect(RuleEvaluation::count())->toBeGreaterThan(0);
    $this->actingAs($this->owner)->get("/issues/{$issue->id}")->assertInertia(fn ($page) => $page->where('stale', false)->where('lastEvaluatedAt', fn ($value) => $value !== null));
});

function contendedJob(int $departmentId): EvaluateRulesJob
{
    return new class($departmentId) extends EvaluateRulesJob
    {
        public ?int $releasedFor = null;

        public int $lockWaitSeconds = 0;

        public function release($delay = 0): void
        {
            $this->releasedFor = $delay;
        }
    };
}

test('an evaluation that cannot take its department lock writes nothing, announces nothing and releases itself', function (string $lockName) {
    importFixture($this->owner, 'history', '2026-09-21 09:00');
    $evaluations = RuleEvaluation::count();
    Event::fake([EvaluationRunCompleted::class]);

    // Another worker holds the lock (an evaluation of the department, or an import writing its records).
    $lock = Cache::lock(str_replace('{id}', (string) $this->f->sales->id, $lockName), 120);
    expect($lock->get())->toBeTrue();

    $job = contendedJob($this->f->sales->id);
    $job->handle(app(SeedDefaultRules::class), app(ReportingCoverage::class), app(BusinessClock::class));

    expect($job->releasedFor)->toBe(15)->and(RuleEvaluation::count())->toBe($evaluations);
    Event::assertNotDispatched(EvaluationRunCompleted::class);

    // Once the lock is free the same job runs and announces once.
    $lock->release();
    $job->handle(app(SeedDefaultRules::class), app(ReportingCoverage::class), app(BusinessClock::class));
    Event::assertDispatchedTimes(EvaluationRunCompleted::class, 1);
})->with(['evaluate:{id}', 'import-processing:department:{id}']);

test('a run that finds the data changing under it stores nothing and releases itself', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');
    setClock('2026-09-28 09:00');
    $evaluations = RuleEvaluation::count();
    Event::fake([EvaluationRunCompleted::class]);

    // Whenever the evaluation reads week totals, another import commits a new record: the fingerprint never settles.
    $departmentId = $this->f->sales->id;
    $importId = DB::table('imports')->value('id');
    $counter = 0;
    DB::listen(function ($query) use (&$counter, $departmentId, $importId) {
        if (str_contains($query->sql, 'coalesce(sum(revenue)') && $counter < 20) {
            $counter++;
            $day = CarbonImmutable::parse('2021-01-01', 'UTC')->addDays($counter)->format('Y-m-d');
            DB::table('sales_records')->insert([
                'department_id' => $departmentId, 'import_id' => $importId, 'branch_code' => 'KL', 'sales_date' => $day,
                'revenue' => '1.00', 'target_revenue' => '1.00', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });

    $job = contendedJob($departmentId);
    $job->handle(app(SeedDefaultRules::class), app(ReportingCoverage::class), app(BusinessClock::class));

    expect($job->releasedFor)->toBe(15)->and(RuleEvaluation::count())->toBe($evaluations);
    Event::assertNotDispatched(EvaluationRunCompleted::class);
});
