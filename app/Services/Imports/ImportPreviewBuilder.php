<?php

namespace App\Services\Imports;

use App\Enums\ImportRowResult;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the preview of a validated import by re-reading its stored (immutable) file, so the same code
 * serves the first preview and every later reopening. Nothing is written.
 */
final class ImportPreviewBuilder
{
    private const MAX_GAPS_PER_WEEK = 50;

    public function __construct(
        private readonly ImportAnalyzer $analyzer,
        private readonly ReportingCoverage $coverage,
        private readonly BusinessClock $clock,
    ) {}

    public function analyze(Import $import): ImportAnalysis
    {
        $department = $import->department;

        return $this->analyzer->analyze(
            Storage::disk('local')->path($import->stored_path),
            $department->id,
            $this->coverage->expectedBranches($department),
            $this->clock->today($import->company->timezone),
        );
    }

    public function build(Import $import, ?ImportAnalysis $analysis = null): ImportPreviewData
    {
        $analysis ??= $this->analyze($import);
        $department = $import->department;
        $calendar = $this->coverage->calendar($import->company);
        $pending = PendingRows::fromClassified($analysis->classified);

        $counts = [
            'total' => $analysis->totalRows,
            'valid' => $analysis->validRows(),
            'invalid' => $analysis->invalidRows,
            'new' => $analysis->count(ImportRowResult::New),
            'corrected' => $analysis->count(ImportRowResult::Corrected),
            'unchanged' => $analysis->count(ImportRowResult::Unchanged),
        ];

        $persisted = [
            'total' => $import->total_rows,
            'valid' => $import->valid_rows,
            'invalid' => $import->invalid_rows,
            'new' => $import->inserted_rows,
            'corrected' => $import->corrected_rows,
            'unchanged' => $import->unchanged_rows,
        ];

        $min = $analysis->minDate();
        $max = $analysis->maxDate();
        $weeks = [];
        $uncovered = ['overdue_branch_days' => null, 'last_due_date' => $calendar->lastDueDate()->format('Y-m-d'), 'first' => null, 'last' => null];

        if ($min !== null && $max !== null) {
            foreach ($this->coverage->weekStates($department, $min, $max, $pending) as $state) {
                $weeks[] = $this->presentWeek($state);
            }

            $freshness = $this->coverage->freshness($department, $pending);
            $uncovered = [
                'overdue_branch_days' => $freshness->overdueBranchDays,
                'last_due_date' => $freshness->lastDueDate->format('Y-m-d'),
                'first' => $freshness->overdueFrom?->format('Y-m-d'),
                'last' => $freshness->overdueTo?->format('Y-m-d'),
            ];
        }

        $nonTrading = count(array_filter(
            $analysis->classified,
            fn (ClassifiedRow $row) => ! $calendar->isTradingDay($row->row->salesDate),
        ));

        return new ImportPreviewData(
            counts: $counts,
            minDate: $min?->format('Y-m-d'),
            maxDate: $max?->format('Y-m-d'),
            branches: $analysis->branches(),
            nonTradingDayRows: $nonTrading,
            weeks: $weeks,
            alreadyImported: $this->alreadyImported($import),
            refreshed: $import->status === ImportStatus::Validated && $counts !== $persisted,
            uncovered: $uncovered,
        );
    }

    /**
     * Week states of a finished import's date range, from the Active_Records at the current clock time.
     *
     * @return list<array<string, mixed>>
     */
    public function weeksOf(Import $import): array
    {
        if ($import->min_date === null || $import->max_date === null) {
            return [];
        }

        return array_map(
            fn (WeekState $state) => $this->presentWeek($state),
            $this->coverage->weekStates(
                $import->department,
                CarbonImmutable::parse($import->min_date->format('Y-m-d'), 'UTC'),
                CarbonImmutable::parse($import->max_date->format('Y-m-d'), 'UTC'),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWeek(WeekState $state): array
    {
        $gaps = array_map(fn (CoverageGap $gap) => [
            'branch_code' => $gap->branchCode,
            'date' => $gap->date->format('Y-m-d'),
            'kind' => $gap->kind,
        ], array_slice($state->gaps, 0, self::MAX_GAPS_PER_WEEK));

        return [
            'week_start' => $state->weekStart->format('Y-m-d'),
            'week_end' => $state->weekEnd->format('Y-m-d'),
            'status' => $state->status->value,
            'completeness' => $state->completeness?->value,
            'expected_branch_days' => $state->expectedBranchDays,
            'present_branch_days' => $state->presentBranchDays,
            'due_branch_days' => $state->dueBranchDays,
            'coverage_pct' => $state->coveragePct,
            'gaps' => $gaps,
            'more_gaps' => max(0, count($state->gaps) - self::MAX_GAPS_PER_WEEK),
        ];
    }

    /**
     * @return list<array{id: int, original_filename: string}>
     */
    private function alreadyImported(Import $import): array
    {
        if ($import->file_hash === null) {
            return [];
        }

        return Import::query()
            ->where('department_id', $import->department_id)
            ->where('file_hash', $import->file_hash)
            ->whereKeyNot($import->id)
            ->whereIn('status', [ImportStatus::Completed->value, ImportStatus::CompletedWithErrors->value])
            ->orderByDesc('id')
            ->get(['id', 'original_filename'])
            ->map(fn (Import $other) => ['id' => $other->id, 'original_filename' => $other->original_filename])
            ->all();
    }
}
