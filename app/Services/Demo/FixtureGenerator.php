<?php

namespace App\Services\Demo;

/**
 * Deterministic demo CSV files (docs/demo-scenario.md section 2). Integer arithmetic only, no clock, no
 * database, no randomness: the output is byte-identical on every run.
 */
final class FixtureGenerator
{
    /** Weekly totals in MYR of weeks W1 to W7, starting Monday 2026-08-03. */
    private const HISTORY_TOTALS = [150000, 152000, 149000, 153000, 151000, 154000, 155000];

    private const HISTORY_START = '2026-08-03';

    /** Branch order within a day, and each branch's daily target. */
    private const BRANCHES = ['KL' => 12000, 'PG' => 8000, 'JB' => 6000];

    /**
     * File name => CSV content (LF line endings, trailing newline, no BOM).
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        $history = [];
        foreach (self::HISTORY_TOTALS as $index => $total) {
            $history = [...$history, ...$this->weekRows($this->monday(self::HISTORY_START, $index * 7), $total)];
        }

        $week8 = $this->weekRows('2026-09-21', 124000);

        return [
            'history.csv' => $this->csv($history),
            'week8-decline.csv' => $this->csv($week8),
            'week8-with-errors.csv' => $this->csv($this->withErrors($week8)),
            'week8-correction.csv' => $this->csv($this->correction($week8)),
            'week9-interim.csv' => $this->csv($this->weekRows('2026-09-28', 138000)),
            'week10-recovery.csv' => $this->csv($this->weekRows('2026-10-05', 150000)),
        ];
    }

    /**
     * Six daily totals: floor(weekly / 6) each, plus 1 from Monday until the remainder is used.
     *
     * @return list<int>
     */
    public function dailyTotals(int $weeklyTotal): array
    {
        $base = intdiv($weeklyTotal, 6);
        $remainder = $weeklyTotal % 6;
        $totals = [];

        for ($day = 0; $day < 6; $day++) {
            $totals[] = $base + ($day < $remainder ? 1 : 0);
        }

        return $totals;
    }

    /**
     * @return array{KL: int, PG: int, JB: int} whole MYR: PG = floor(D*8/26), JB = floor(D*6/26), KL the rest
     */
    public function split(int $dailyTotal): array
    {
        $pg = intdiv($dailyTotal * 8, 26);
        $jb = intdiv($dailyTotal * 6, 26);

        return ['KL' => $dailyTotal - $pg - $jb, 'PG' => $pg, 'JB' => $jb];
    }

    /**
     * The 18 rows of one trading week (Monday to Saturday, KL then PG then JB).
     *
     * @return list<array{date: string, branch: string, revenue: int, target: int, target_text: string}>
     */
    private function weekRows(string $monday, int $weeklyTotal): array
    {
        $rows = [];
        foreach ($this->dailyTotals($weeklyTotal) as $offset => $dailyTotal) {
            $date = $this->monday($monday, $offset);
            foreach ($this->split($dailyTotal) as $branch => $revenue) {
                $rows[] = [
                    'date' => $date,
                    'branch' => $branch,
                    'revenue' => $revenue,
                    'target' => self::BRANCHES[$branch],
                    'target_text' => number_format(self::BRANCHES[$branch], 2, '.', ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * week8-correction: KL Wednesday 2026-09-23 and PG Thursday 2026-09-24 carry +1,000.00 revenue.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function correction(array $rows): array
    {
        return array_map(function (array $row): array {
            if (($row['date'] === '2026-09-23' && $row['branch'] === 'KL') || ($row['date'] === '2026-09-24' && $row['branch'] === 'PG')) {
                $row['revenue'] += 1000;
            }

            return $row;
        }, $rows);
    }

    /**
     * week8-with-errors: the 18 W8 rows (JB Saturday with a non-numeric target on row 19), a row with an
     * impossible date (row 20) and a Sunday row with negative revenue (row 21).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withErrors(array $rows): array
    {
        $last = array_key_last($rows);
        $rows[$last]['target_text'] = 'TBD';

        $rows[] = ['date' => '2026-09-31', 'branch' => 'KL', 'revenue' => 20667, 'target' => 12000, 'target_text' => '12000.00'];
        $rows[] = ['date' => '2026-09-27', 'branch' => 'KL', 'revenue' => -1500, 'target' => 12000, 'target_text' => '12000.00'];

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function csv(array $rows): string
    {
        $lines = [implode(',', array_keys(config('imports.columns')))];

        foreach ($rows as $row) {
            // order_count is left empty (unknown) in every fixture.
            $lines[] = implode(',', [$row['date'], $row['branch'], $this->money($row['revenue']), $row['target_text'], '']);
        }

        return implode("\n", $lines)."\n";
    }

    /** Whole MYR as a two-decimal string, from integers (no float). */
    private function money(int $amount): string
    {
        return ($amount < 0 ? '-' : '').abs($amount).'.00';
    }

    /** A date string offset by whole days from a start date (calendar arithmetic, no clock). */
    private function monday(string $start, int $days): string
    {
        return date('Y-m-d', strtotime("{$start} +{$days} days UTC"));
    }
}
