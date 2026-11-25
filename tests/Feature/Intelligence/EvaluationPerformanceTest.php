<?php

use App\Actions\Intelligence\DispatchEvaluation;
use App\Models\RuleEvaluation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->f = identity();
    $this->import = importCsv($this->f->owner, weekCsv('2026-09-14', '1000.00'));
});

/**
 * Add historical rows directly (this test measures evaluation, not import) so the table holds $rows active records.
 */
function seedHistory(int $departmentId, int $importId, int $rows): void
{
    $now = now()->format('Y-m-d H:i:s');
    $batch = [];
    $day = CarbonImmutable::parse('2005-01-01', 'UTC');

    for ($i = 0; $i < $rows; $i++) {
        $date = $day->addDays(intdiv($i, 3))->format('Y-m-d');
        $batch[] = [
            'department_id' => $departmentId, 'import_id' => $importId, 'branch_code' => ['KL', 'PG', 'JB'][$i % 3], 'sales_date' => $date,
            'revenue' => '1000.00', 'target_revenue' => '1000.00', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ];
        if (count($batch) === 1000) {
            DB::table('sales_records')->insert($batch);
            $batch = [];
        }
    }

    $batch === [] || DB::table('sales_records')->insert($batch);
}

function evaluationCost(int $departmentId): array
{
    setClock('2026-09-28 09:00');
    DB::flushQueryLog();
    DB::enableQueryLog();
    $started = microtime(true);
    app(DispatchEvaluation::class)->handle($departmentId);
    $seconds = microtime(true) - $started;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [$seconds, $queries];
}

test('an evaluation over 10,000 active records takes under 10 seconds with a bounded number of queries', function () {
    [, $small] = evaluationCost($this->f->sales->id);

    seedHistory($this->f->sales->id, $this->import->id, 10000);
    expect(DB::table('sales_records')->where('is_active', true)->count())->toBeGreaterThanOrEqual(10000);

    [$seconds, $large] = evaluationCost($this->f->sales->id);

    expect($seconds)->toBeLessThan(10)
        // The same rules cost the same number of queries however many rows the table holds (the ids are only
        // read, never queried one by one). A run with changed data also writes new evaluations.
        ->and($large)->toBeLessThanOrEqual($small + 10)
        ->and(RuleEvaluation::count())->toBeGreaterThan(0);
});

test('only the department of the job is read: another department with many records does not change the evaluation', function () {
    seedHistory($this->f->support->id, $this->import->id, 3000);
    app(DispatchEvaluation::class)->handle($this->f->sales->id);

    $evidence = latestEvaluation('weekly_revenue_decline')->evidence;
    $supportIds = DB::table('sales_records')->where('department_id', $this->f->support->id)->pluck('id')->all();

    expect(array_intersect($evidence['contributingRecordIds'], $supportIds))->toBe([])
        ->and(RuleEvaluation::where('department_id', $this->f->support->id)->count())->toBe(0);
});
