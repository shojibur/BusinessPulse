<?php

namespace App\Jobs;

use App\Enums\ImportRowResult;
use App\Enums\ImportStatus;
use App\Events\Imports\ImportCompleted;
use App\Events\Imports\ImportFailed;
use App\Models\Import;
use App\Models\SalesRecord;
use App\Services\Imports\ExpectedBranches;
use App\Services\Imports\ImportAnalysis;
use App\Services\Imports\ImportPreviewBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Commits a confirmed import in one transaction: inserts new rows, supersedes and replaces corrected rows,
 * leaves unchanged rows alone, never deletes. Idempotent: it does nothing unless the import is `processing`.
 * Retries live in the queue only (3 tries, backoff 10/30/60 s); `retry_after` (300 s) exceeds the timeout.
 */
final class ProcessImportJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public const MESSAGE_NO_BRANCHES = 'Expected branches are not configured';

    public const MESSAGE_NO_VALID_ROWS = 'No valid rows to import';

    public const MESSAGE_FILE_MISSING = 'The source file is unavailable. Please upload the file again.';

    public const MESSAGE_CONFLICT = 'Data changed during import. Please upload again';

    public const MESSAGE_GENERIC = 'The import could not be processed. Please try again or upload the file again.';

    public int $timeout = 120;

    public int $tries = 3;

    // Longer than the whole retry window (3 x 120 s plus backoff) so a duplicate dispatch cannot overlap.
    public int $uniqueFor = 900;

    public function __construct(public readonly int $importId)
    {
        $this->onQueue('default');
        $this->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return (string) $this->importId;
    }

    public function handle(ImportPreviewBuilder $previews): void
    {
        $import = Import::query()->with(['department', 'company'])->find($this->importId);

        // Stale or repeated dispatch: only a processing import is worked on.
        if ($import === null || $import->status !== ImportStatus::Processing) {
            return;
        }

        // One writer per department, so two imports can never both supersede the same record.
        $lock = Cache::lock("import-processing:department:{$import->department_id}", $this->timeout + 30);

        if (! $lock->get()) {
            $this->release(10);

            return;
        }

        try {
            $this->process($import->refresh()->load(['department', 'company']), $previews);
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        $import = Import::query()->find($this->importId);

        if ($import === null || $import->status !== ImportStatus::Processing) {
            return;
        }

        // Log the class only: messages can contain SQL, paths or cell values.
        Log::error('Import processing failed', ['import_id' => $this->importId, 'exception' => $exception::class]);

        $this->markFailed($import, self::MESSAGE_GENERIC);
    }

    private function process(Import $import, ImportPreviewBuilder $previews): void
    {
        if ($import->status !== ImportStatus::Processing) {
            return;
        }

        if ($this->expectedBranchesMissing($import)) {
            $this->markFailed($import, self::MESSAGE_NO_BRANCHES);

            return;
        }

        if ($import->stored_path === '' || ! Storage::disk('local')->exists($import->stored_path)) {
            $this->markFailed($import, self::MESSAGE_FILE_MISSING);

            return;
        }

        // Re-validate and re-classify against the Active_Records as they are now, not as at preview time.
        $analysis = $previews->analyze($import);

        if ($analysis->validRows() === 0) {
            $this->markFailed($import, self::MESSAGE_NO_VALID_ROWS);

            return;
        }

        try {
            DB::transaction(fn () => $this->commit($import, $analysis));
        } catch (UniqueConstraintViolationException|StaleImportData) {
            // The partial unique index (or a stale record) stops a concurrent change; the transaction rolled back.
            $this->markFailed($import, self::MESSAGE_CONFLICT);

            return;
        }

        $import->refresh();

        ImportCompleted::dispatch(
            $import->id,
            $import->department_id,
            $import->status,
            $import->inserted_rows,
            $import->corrected_rows,
            $import->unchanged_rows,
            $import->invalid_rows,
            $import->min_date === null ? null : CarbonImmutable::parse($import->min_date->format('Y-m-d'), 'UTC'),
            $import->max_date === null ? null : CarbonImmutable::parse($import->max_date->format('Y-m-d'), 'UTC'),
        );
    }

    private function commit(Import $import, ImportAnalysis $analysis): void
    {
        $now = now();
        $chunkSize = (int) config('imports.write_chunk_size');
        $new = [];
        $corrected = [];
        $supersedeIds = [];

        foreach ($analysis->classified as $classified) {
            $row = [
                'department_id' => $import->department_id,
                'import_id' => $import->id,
                'branch_code' => $classified->row->branchCode,
                'sales_date' => $classified->row->salesDate->format('Y-m-d'),
                'revenue' => $classified->row->revenue,
                'target_revenue' => $classified->row->targetRevenue,
                'order_count' => $classified->row->orderCount,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            match ($classified->result) {
                ImportRowResult::New => $new[] = $row,
                ImportRowResult::Corrected => [$corrected[] = $row, $supersedeIds[] = $classified->activeRecord->id],
                default => null,
            };
        }

        // Old versions are superseded first (never deleted) so the unique index only ever sees one active row.
        foreach (array_chunk($supersedeIds, $chunkSize) as $ids) {
            $updated = SalesRecord::query()->whereIn('id', $ids)->where('is_active', true)->update([
                'is_active' => false,
                'superseded_by_import_id' => $import->id,
                'superseded_at' => $now,
                'updated_at' => $now,
            ]);

            if ($updated !== count($ids)) {
                // A record we meant to correct is no longer active: the data changed under us.
                throw new StaleImportData;
            }
        }

        foreach (array_chunk([...$new, ...$corrected], $chunkSize) as $rows) {
            SalesRecord::query()->insert($rows);
        }

        // Replace the errors recorded at preview time with those of this run.
        DB::table('import_errors')->where('import_id', $import->id)->delete();
        foreach (array_chunk($analysis->errors, 500) as $chunk) {
            DB::table('import_errors')->insert(array_map(fn ($error) => [
                'import_id' => $import->id,
                'row_number' => $error->rowNumber,
                'column' => $error->column,
                'code' => $error->code,
                'message' => $error->message,
                'raw_row' => json_encode((object) $error->rawRow),
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $import->update([
            'total_rows' => $analysis->totalRows,
            'valid_rows' => $analysis->validRows(),
            'invalid_rows' => $analysis->invalidRows,
            'inserted_rows' => count($new),
            'corrected_rows' => count($corrected),
            'unchanged_rows' => $analysis->count(ImportRowResult::Unchanged),
            'min_date' => $analysis->minDate()?->format('Y-m-d'),
            'max_date' => $analysis->maxDate()?->format('Y-m-d'),
            'status' => $analysis->invalidRows === 0 ? ImportStatus::Completed : ImportStatus::CompletedWithErrors,
            'completed_at' => $now,
            'error_message' => null,
        ]);
    }

    private function expectedBranchesMissing(Import $import): bool
    {
        return app(ExpectedBranches::class)->get($import->department) === [];
    }

    private function markFailed(Import $import, string $message): void
    {
        $import->update(['status' => ImportStatus::Failed, 'error_message' => $message]);

        ImportFailed::dispatch($import->id, $import->department_id);
    }
}
