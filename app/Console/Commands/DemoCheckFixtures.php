<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Demo\FixtureGenerator;
use App\Services\Imports\CsvImportReader;
use App\Services\Imports\ImportRejectedException;
use App\Services\Imports\ImportRowValidator;
use App\Services\Imports\ReportingCalendar;
use App\Services\Imports\ReportingCoverage;
use App\Services\Imports\ValidRow;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

#[Signature('demo:check-fixtures {--write : Rewrite the six CSV files from the generator first (never expected.json)}')]
#[Description('Verify the demo CSV fixtures against the generator and the literal values in database/demo/expected.json')]
class DemoCheckFixtures extends Command
{
    /** @var list<string> */
    private array $mismatches = [];

    /** @var array<string, list<ValidRow>> */
    private array $validRowsByFixture = [];

    public function handle(FixtureGenerator $generator, CsvImportReader $reader, ImportRowValidator $validator, ReportingCoverage $coverage): int
    {
        $directory = base_path('database/demo');
        $expected = json_decode((string) file_get_contents($directory.'/expected.json'), true, 512, JSON_THROW_ON_ERROR);
        $generated = $generator->files();

        if ($this->option('write')) {
            foreach ($generated as $name => $content) {
                file_put_contents("{$directory}/{$name}", $content);
            }
            $this->components->info('Rewrote the six CSV files from the generator.');
        }

        $branches = $expected['expectedBranches'];

        foreach ($expected['fixtures'] as $name => $fixture) {
            $path = "{$directory}/{$fixture['file']}";

            // 1. The committed file is exactly what the generator produces.
            $onDisk = is_file($path) ? (string) file_get_contents($path) : null;
            $this->compare($name, 'file bytes equal generator output', 'identical', $onDisk === ($generated[$fixture['file']] ?? null) ? 'identical' : 'different');

            if ($onDisk === null) {
                continue;
            }

            // 2. The real reader and validator at the fixture clock.
            $clock = CarbonImmutable::parse($fixture['clock'], $expected['calendar']['timezone']);

            try {
                $reader->validate(new UploadedFile($path, $fixture['file'], 'text/csv', null, true));
            } catch (ImportRejectedException $e) {
                $this->mismatch($name, 'file-level validation', 'accepted', $e->reason);

                continue;
            }

            $parsed = $this->parse($reader, $validator, $path, $branches, $clock);
            $this->validRowsByFixture[$name] = $parsed['valid'];

            $this->compare($name, 'total rows', $fixture['totalRows'], $parsed['total']);
            $this->compare($name, 'valid rows', $fixture['validRows'], count($parsed['valid']));
            $this->compare($name, 'invalid rows', $fixture['invalidRows'], $parsed['invalid']);
            $this->compare($name, 'error list', json_encode($fixture['errors']), json_encode($parsed['errors']));

            // 3. Weekly totals and branch-day counts of the valid rows (decimal strings, bcmath).
            $totals = [];
            $days = [];
            foreach ($parsed['valid'] as $row) {
                $week = ReportingCalendar::weekStart($row->salesDate)->format('Y-m-d');
                $totals[$week] = bcadd($totals[$week] ?? '0', $row->revenue, 2);
                $days[$week] = ($days[$week] ?? 0) + 1;
            }
            ksort($totals);
            ksort($days);
            $this->compare($name, 'weekly totals', json_encode($fixture['weeklyTotals']), json_encode($totals));
            $this->compare($name, 'branch-day counts', json_encode($fixture['branchDays']), json_encode($days));
        }

        // 4. Week states at each fixture clock, after overlaying the fixtures it follows (no database).
        foreach ($expected['fixtures'] as $name => $fixture) {
            if (! isset($this->validRowsByFixture[$name])) {
                continue;
            }

            $clock = CarbonImmutable::parse($fixture['clock'], $expected['calendar']['timezone']);
            $calendar = ReportingCalendar::forCompany($this->calendarCompany($expected['calendar']), $clock);

            $rows = [];
            foreach ([...$fixture['after'], $name] as $source) {
                foreach ($this->validRowsByFixture[$source] ?? [] as $row) {
                    $rows[$row->identity()] = ['branch_code' => $row->branchCode, 'sales_date' => $row->salesDate->format('Y-m-d')];
                }
            }

            foreach ($fixture['weekStates'] as $weekStart => $state) {
                $actual = $coverage->weekStateForRows($calendar, $branches, CarbonImmutable::parse($weekStart, 'UTC'), array_values($rows));
                $this->compare(
                    $name,
                    "week state {$weekStart}",
                    $state['status'].'/'.($state['completeness'] ?? 'none'),
                    $actual->status->value.'/'.($actual->completeness?->value ?? 'none'),
                );
            }
        }

        if ($this->mismatches === []) {
            $this->components->info('All demo fixtures match expected.json.');

            return self::SUCCESS;
        }

        foreach ($this->mismatches as $line) {
            $this->line($line);
        }
        $this->components->error(count($this->mismatches).' mismatch(es).');

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $branches
     * @return array{total: int, valid: list<ValidRow>, invalid: int, errors: list<array{row: int, column: string|null, code: string}>}
     */
    private function parse(CsvImportReader $reader, ImportRowValidator $validator, string $path, array $branches, CarbonImmutable $clock): array
    {
        $today = CarbonImmutable::parse($clock->format('Y-m-d'), 'UTC');
        $total = 0;
        $valid = [];
        $raw = [];
        $errors = [];

        foreach ($reader->rows($path) as [$rowNumber, $cells, $positional]) {
            $total++;
            $result = $validator->validate($rowNumber, $cells, $branches, $today, $positional);
            $raw[$rowNumber] = $cells ?? [];
            $result->isValid() ? $valid[$rowNumber] = $result->row : $errors[$rowNumber] = $result->errors;
        }

        foreach ($validator->detectDuplicateIdentities($valid, $raw) as $rowNumber => $duplicateErrors) {
            $errors[$rowNumber] = $duplicateErrors;
            unset($valid[$rowNumber]);
        }

        ksort($errors);
        $list = [];
        foreach ($errors as $rowErrors) {
            foreach ($rowErrors as $error) {
                $list[] = ['row' => $error->rowNumber, 'column' => $error->column, 'code' => $error->code];
            }
        }
        ksort($valid);

        return ['total' => $total, 'valid' => array_values($valid), 'invalid' => count($errors), 'errors' => $list];
    }

    /**
     * An in-memory company carrying only the calendar (nothing is read from or written to the database).
     *
     * @param  array{timezone: string, tradingDays: list<int>, cutoffTime: string, cutoffLagDays: int}  $calendar
     */
    private function calendarCompany(array $calendar): Company
    {
        return new Company([
            'timezone' => $calendar['timezone'],
            'trading_days' => $calendar['tradingDays'],
            'reporting_cutoff_time' => $calendar['cutoffTime'],
            'reporting_cutoff_lag_days' => $calendar['cutoffLagDays'],
        ]);
    }

    private function compare(string $fixture, string $check, mixed $expected, mixed $actual): void
    {
        if ((string) $expected !== (string) $actual) {
            $this->mismatch($fixture, $check, (string) $expected, (string) $actual);
        }
    }

    private function mismatch(string $fixture, string $check, string $expected, string $actual): void
    {
        $this->mismatches[] = "{$fixture} | {$check} | expected: {$expected} | actual: {$actual}";
    }
}
