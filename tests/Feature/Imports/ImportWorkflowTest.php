<?php

use App\Actions\Imports\ConfirmImport;
use App\Actions\Imports\CreateImportFromUpload;
use App\Actions\Imports\RetryImport;
use App\Enums\ImportStatus;
use App\Events\Imports\ImportCompleted;
use App\Events\Imports\ImportFailed;
use App\Jobs\ProcessImportJob;
use App\Models\SalesRecord;
use App\Services\Imports\ImportActionException;
use App\Services\Imports\ImportPreviewBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->f = identity();
    $this->owner = $this->f->owner;
});

function weekRevenue(string $from, string $to): string
{
    $sum = '0';
    foreach (SalesRecord::where('is_active', true)->whereBetween('sales_date', [$from, $to])->pluck('revenue') as $revenue) {
        $sum = bcadd($sum, (string) $revenue, 2);
    }

    return $sum;
}

test('a valid file is validated, previewed and then imported by the queued job', function () {
    setClock('2026-09-21 09:00');
    Event::fake([ImportCompleted::class]);

    $import = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('history'));

    expect($import->status)->toBe(ImportStatus::Validated)
        ->and([$import->total_rows, $import->valid_rows, $import->invalid_rows])->toBe([126, 126, 0])
        ->and([$import->inserted_rows, $import->corrected_rows, $import->unchanged_rows])->toBe([126, 0, 0])
        ->and($import->min_date->format('Y-m-d'))->toBe('2026-08-03')
        ->and($import->max_date->format('Y-m-d'))->toBe('2026-09-19')
        ->and($import->company_id)->toBe($this->f->company->id)
        ->and($import->department_id)->toBe($this->f->sales->id)
        ->and($import->uploaded_by)->toBe($this->owner->id)
        ->and($import->file_hash)->toHaveLength(64)
        ->and(SalesRecord::count())->toBe(0); // nothing is saved before confirmation

    expect(Storage::disk('local')->exists($import->stored_path))->toBeTrue()
        ->and($import->stored_path)->toStartWith("imports/{$import->id}/")
        ->and($import->stored_path)->not->toContain('history');

    app(ConfirmImport::class)->handle($import);
    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->completed_at)->not->toBeNull()
        ->and(SalesRecord::where('is_active', true)->count())->toBe(126)
        ->and(weekRevenue('2026-08-03', '2026-08-08'))->toBe('150000.00')
        ->and(weekRevenue('2026-09-14', '2026-09-19'))->toBe('155000.00');

    Event::assertDispatched(ImportCompleted::class, fn (ImportCompleted $e) => $e->importId === $import->id
        && $e->status === ImportStatus::Completed && $e->insertedRows === 126 && $e->invalidRows === 0
        && $e->minDate->format('Y-m-d') === '2026-08-03' && $e->actorUserId === null);
});

test('invalid rows are reported with row, column and code and the valid rows still import', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');

    $import = importFixture($this->owner, 'week8-with-errors', '2026-09-28 09:00');

    expect($import->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and([$import->total_rows, $import->valid_rows, $import->invalid_rows])->toBe([20, 17, 3])
        ->and($import->inserted_rows)->toBe(17)
        ->and($import->errors()->orderBy('row_number')->get(['row_number', 'column', 'code'])->map->only(['row_number', 'column', 'code'])->all())->toBe([
            ['row_number' => 19, 'column' => 'target_revenue', 'code' => 'invalid_number'],
            ['row_number' => 20, 'column' => 'sales_date', 'code' => 'invalid_date'],
            ['row_number' => 21, 'column' => 'revenue', 'code' => 'negative_value'],
        ])
        ->and($import->errors()->where('row_number', 19)->first()->raw_row['target_revenue'])->toBe('TBD')
        ->and(SalesRecord::where('import_id', $import->id)->count())->toBe(17);
});

test('importing the same file twice classifies everything unchanged and does not inflate revenue', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');
    $first = importFixture($this->owner, 'week8-decline', '2026-09-28 09:00');
    $revenue = activeRevenue();
    $records = SalesRecord::count();

    $second = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('week8-decline'));

    expect($second->unchanged_rows)->toBe(18)->and($second->inserted_rows)->toBe(0)->and($second->corrected_rows)->toBe(0)
        ->and($second->file_hash)->toBe($first->file_hash);

    $preview = app(ImportPreviewBuilder::class)->build($second)->toArray();
    expect($preview['already_imported'])->toBe([['id' => $first->id, 'original_filename' => 'week8-decline.csv']]);

    app(ConfirmImport::class)->handle($second);

    expect($second->refresh()->status)->toBe(ImportStatus::Completed)
        ->and($second->unchanged_rows)->toBe(18)
        ->and(SalesRecord::count())->toBe($records)
        ->and(SalesRecord::where('is_active', true)->count())->toBe(144)
        ->and(activeRevenue())->toBe($revenue)
        ->and(weekRevenue('2026-09-21', '2026-09-26'))->toBe('124000.00');
});

test('a correction supersedes the old rows, keeps history and leaves one active row per identity', function () {
    importFixture($this->owner, 'history', '2026-09-21 09:00');
    $first = importFixture($this->owner, 'week8-decline', '2026-09-28 09:00');

    $second = importFixture($this->owner, 'week8-correction');

    expect([$second->unchanged_rows, $second->corrected_rows, $second->inserted_rows])->toBe([16, 2, 0]);

    $week = SalesRecord::whereBetween('sales_date', ['2026-09-21', '2026-09-26']);
    expect((clone $week)->where('is_active', true)->count())->toBe(18)
        ->and((clone $week)->count())->toBe(20) // nothing was deleted
        ->and(weekRevenue('2026-09-21', '2026-09-26'))->toBe('126000.00');

    $old = SalesRecord::where('is_active', false)->orderBy('sales_date')->get();
    expect($old)->toHaveCount(2)
        ->and($old->pluck('superseded_by_import_id')->unique()->all())->toBe([$second->id])
        ->and($old->pluck('import_id')->unique()->all())->toBe([$first->id])
        ->and($old->every(fn ($row) => $row->superseded_at !== null))->toBeTrue()
        ->and([$old[0]->branch_code, $old[0]->sales_date->format('Y-m-d'), $old[0]->revenue])->toBe(['KL', '2026-09-23', '9539.00'])
        ->and([$old[1]->branch_code, $old[1]->sales_date->format('Y-m-d'), $old[1]->revenue])->toBe(['PG', '2026-09-24', '6359.00'])
        ->and(SalesRecord::where('is_active', true)->where('branch_code', 'KL')->where('sales_date', '2026-09-23')->value('revenue'))->toBe('10539.00');
});

test('the database allows only one active record per identity but any number of superseded ones', function () {
    $import = importFixture($this->owner, 'history', '2026-09-21 09:00');
    $record = SalesRecord::where('is_active', true)->first();

    $duplicate = fn () => SalesRecord::create([
        'department_id' => $record->department_id, 'import_id' => $import->id, 'branch_code' => $record->branch_code,
        'sales_date' => $record->sales_date->format('Y-m-d'), 'revenue' => '1.00', 'target_revenue' => '1.00', 'is_active' => true,
    ]);

    expect(fn () => DB::transaction($duplicate))->toThrow(QueryException::class);

    // Inactive history rows are not restricted.
    SalesRecord::create([
        'department_id' => $record->department_id, 'import_id' => $import->id, 'branch_code' => $record->branch_code,
        'sales_date' => $record->sales_date->format('Y-m-d'), 'revenue' => '1.00', 'target_revenue' => '1.00', 'is_active' => false,
    ]);
    expect(SalesRecord::count())->toBe(127);
});

test('confirming twice queues and writes once', function () {
    setClock('2026-09-21 09:00');
    Queue::fake();

    $import = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('history'));
    $confirm = app(ConfirmImport::class);

    $confirm->handle($import);
    $confirm->handle($import); // double click

    Queue::assertPushed(ProcessImportJob::class, 1);
    expect($import->refresh()->status)->toBe(ImportStatus::Processing);
});

test('a stale preview cannot create a second active record for the same identity', function () {
    setClock('2026-09-28 09:00');
    importFixture($this->owner, 'history');

    // Two previews of the same file are validated before either is processed: both say all rows are new.
    $a = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('week8-decline'));
    $b = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('week8-decline'));
    expect([$a->inserted_rows, $b->inserted_rows])->toBe([18, 18]);

    app(ConfirmImport::class)->handle($a);
    app(ConfirmImport::class)->handle($b);

    // B re-classified against the records A wrote, so it inserted nothing.
    expect($b->refresh()->unchanged_rows)->toBe(18)->and($b->inserted_rows)->toBe(0)
        ->and(SalesRecord::where('is_active', true)->whereBetween('sales_date', ['2026-09-21', '2026-09-26'])->count())->toBe(18)
        ->and(weekRevenue('2026-09-21', '2026-09-26'))->toBe('124000.00');
});

test('overlapping imports processed in the opposite order still end with one active record each', function () {
    setClock('2026-09-28 09:00');
    importFixture($this->owner, 'history');

    $decline = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('week8-decline'));
    $correction = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('week8-correction'));

    app(ConfirmImport::class)->handle($correction);
    app(ConfirmImport::class)->handle($decline); // last confirmed wins: it supersedes the corrected rows

    $active = SalesRecord::where('is_active', true)->whereBetween('sales_date', ['2026-09-21', '2026-09-26']);
    expect($active->count())->toBe(18)
        ->and($decline->refresh()->corrected_rows)->toBe(2)
        ->and(weekRevenue('2026-09-21', '2026-09-26'))->toBe('124000.00')
        ->and(DB::selectOne('select count(*) c from (select 1 from sales_records where is_active group by department_id, branch_code, sales_date having count(*) > 1) d')->c)->toBe(0);
});

test('a failure inside the transaction rolls everything back and a retry recovers', function () {
    setClock('2026-09-28 09:00');
    importFixture($this->owner, 'history');
    $before = SalesRecord::count();
    Event::fake([ImportFailed::class, ImportCompleted::class]);

    // A trigger makes one specific row fail halfway through the writes.
    DB::unprepared("CREATE FUNCTION bp_fail_one() RETURNS trigger AS \$\$ BEGIN IF NEW.branch_code = 'JB' AND NEW.sales_date = '2026-09-26' THEN RAISE EXCEPTION 'simulated failure'; END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql");
    DB::unprepared('CREATE TRIGGER bp_fail_one BEFORE INSERT ON sales_records FOR EACH ROW EXECUTE FUNCTION bp_fail_one()');
    config(['imports.write_chunk_size' => 5]);

    $import = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('week8-decline'));
    expect(fn () => app(ConfirmImport::class)->handle($import))->toThrow(QueryException::class);

    $job = new ProcessImportJob($import->id);
    $job->failed(new RuntimeException('retries exhausted'));
    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->error_message)->toBe(ProcessImportJob::MESSAGE_GENERIC)
        ->and($import->error_message)->not->toContain('simulated')
        ->and(SalesRecord::count())->toBe($before); // the earlier chunks were rolled back

    Event::assertDispatched(ImportFailed::class, fn (ImportFailed $e) => $e->importId === $import->id);
    Event::assertNotDispatched(ImportCompleted::class);

    DB::unprepared('DROP TRIGGER bp_fail_one ON sales_records');
    app(RetryImport::class)->handle($import);

    expect($import->refresh()->status)->toBe(ImportStatus::Completed)
        ->and($import->error_message)->toBeNull()
        ->and(SalesRecord::count())->toBe($before + 18);
});

test('a missing stored file fails the import with a sanitized message and no data change', function () {
    setClock('2026-09-21 09:00');
    Queue::fake();
    $import = app(CreateImportFromUpload::class)->handle($this->owner, fixtureUpload('history'));
    Storage::disk('local')->delete($import->stored_path);
    Event::fake([ImportFailed::class]);

    app(ConfirmImport::class)->handle($import);
    (new ProcessImportJob($import->id))->handle(app(ImportPreviewBuilder::class));
    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->error_message)->toBe(ProcessImportJob::MESSAGE_FILE_MISSING)
        ->and($import->error_message)->not->toContain($import->stored_path)
        ->and(SalesRecord::count())->toBe(0);
    Event::assertDispatched(ImportFailed::class);

    expect(fn () => app(RetryImport::class)->handle($import))->toThrow(ImportActionException::class, 'no longer available');
});
