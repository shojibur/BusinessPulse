<?php

namespace App\Services\Imports;

use App\Models\Company;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The company reporting calendar at one instant. Pure: no database access, no clock read.
 *
 * A trading day D is due when `now` is at or after (D + lag days) at the cutoff time in the company
 * timezone. Future days, non-trading days and days whose cutoff has not passed are never due, so they
 * are never reported missing (docs/stage-1-refinements.md R2).
 */
final class ReportingCalendar
{
    /**
     * @param  list<int>  $tradingDays  ISO weekdays 1 to 7
     */
    public function __construct(
        private readonly string $timezone,
        private readonly array $tradingDays,
        private readonly string $cutoffTime,
        private readonly int $lagDays,
        private readonly CarbonImmutable $now,
    ) {
        if ($tradingDays === []) {
            throw new InvalidArgumentException('A reporting calendar needs at least one trading day.');
        }
    }

    public static function forCompany(Company $company, CarbonImmutable $now): self
    {
        return new self(
            $company->timezone,
            $company->trading_days,
            $company->reporting_cutoff_time,
            $company->reporting_cutoff_lag_days,
            $now,
        );
    }

    public function isTradingDay(CarbonImmutable $date): bool
    {
        return in_array($date->dayOfWeekIso, $this->tradingDays, true);
    }

    public function isDue(CarbonImmutable $date): bool
    {
        if (! $this->isTradingDay($date)) {
            return false;
        }

        $cutoff = CarbonImmutable::parse($date->format('Y-m-d').' '.$this->cutoffTime, $this->timezone)
            ->addDays($this->lagDays);

        return $this->now >= $cutoff;
    }

    /**
     * The latest due trading day.
     */
    public function lastDueDate(): CarbonImmutable
    {
        $day = CarbonImmutable::parse($this->now->setTimezone($this->timezone)->format('Y-m-d'), $this->timezone);

        // A due day is at most (lag + 7) days back: every week has at least one trading day.
        for ($i = 0; $i <= $this->lagDays + 8; $i++) {
            $candidate = $day->subDays($i);
            if ($this->isDue($candidate)) {
                return $this->asDate($candidate);
            }
        }

        throw new InvalidArgumentException('No due trading day found for the reporting calendar.');
    }

    /**
     * The trading dates of the ISO week containing the given date.
     *
     * @return list<CarbonImmutable>
     */
    public function tradingDates(CarbonImmutable $weekStart): array
    {
        $monday = self::weekStart($weekStart);
        $dates = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $monday->addDays($i);
            if ($this->isTradingDay($date)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    public function isWeekClosed(CarbonImmutable $weekStart): bool
    {
        foreach ($this->tradingDates($weekStart) as $date) {
            if (! $this->isDue($date)) {
                return false;
            }
        }

        return true;
    }

    /** The ISO Monday of the week containing the date, as a date-only value. */
    public static function weekStart(CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d'), 'UTC')->subDays($date->dayOfWeekIso - 1);
    }

    /** Dates are plain calendar dates; the timezone only matters for the cutoff instant. */
    private function asDate(CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d'), 'UTC');
    }
}
