<?php

namespace App\Services\Meetings;

use App\Enums\DeadlineResolution;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns a spoken deadline phrase into a date from the MEETING date and the company timezone only. The system clock is
 * never read, so the same historical meeting always resolves the same way. Only a documented set of English phrases is
 * recognized; anything else is ambiguous and a person decides in S07. A result earlier than the meeting date is
 * ambiguous. Pure and deterministic.
 */
final class DeadlineResolver
{
    private const WEEKDAYS = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7];

    private const DAY_PATTERN = 'monday|tuesday|wednesday|thursday|friday|saturday|sunday';

    public function __construct(private readonly TextNormalizer $text) {}

    public function resolve(?string $phrase, CarbonInterface|string $meetingDate, Company $company): DeadlineResult
    {
        if ($phrase === null || trim($phrase) === '') {
            return new DeadlineResult(DeadlineResolution::None, null);
        }

        $start = CarbonImmutable::parse(is_string($meetingDate) ? $meetingDate : $meetingDate->format('Y-m-d'), $company->timezone)->startOfDay();
        $raw = trim($phrase);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $raw, $company->timezone);

            return $date !== false && $date->format('Y-m-d') === $raw ? $this->finish($date, $start) : $this->ambiguous();
        }

        $p = $this->text->normalize($phrase);

        if ($p === 'today') {
            return $this->finish($start, $start);
        }

        if ($p === 'tomorrow') {
            return $this->finish($start->addDay(), $start);
        }

        if (preg_match('/^(?:(?:by|on|this) )?('.self::DAY_PATTERN.')$/', $p, $m) === 1) {
            $delta = (self::WEEKDAYS[$m[1]] - $start->dayOfWeekIso + 7) % 7;

            return $this->finish($start->addDays($delta), $start);
        }

        if (preg_match('/^(?:(?:by|on) )?next ('.self::DAY_PATTERN.')$/', $p, $m) === 1) {
            return $this->finish($start->startOfWeek()->addDays(7 + self::WEEKDAYS[$m[1]] - 1), $start);
        }

        if (preg_match('/^(?:by )?end of (?:the )?week$/', $p) === 1) {
            return $this->finish($this->lastTradingDay($start, $company), $start);
        }

        if (preg_match('/^(?:by )?end of next week$/', $p) === 1) {
            return $this->finish($this->lastTradingDay($start->addDays(7), $company), $start);
        }

        return $this->ambiguous();
    }

    /** The last trading weekday of the ISO week containing the date, or null when no trading days are configured. */
    private function lastTradingDay(CarbonImmutable $anyDateInWeek, Company $company): ?CarbonImmutable
    {
        $days = $company->trading_days;

        return $days === [] ? null : $anyDateInWeek->startOfWeek()->addDays((int) max($days) - 1);
    }

    private function finish(?CarbonImmutable $date, CarbonImmutable $start): DeadlineResult
    {
        if ($date === null || $date->lt($start)) {
            return $this->ambiguous();
        }

        return new DeadlineResult(DeadlineResolution::Resolved, $date->startOfDay());
    }

    private function ambiguous(): DeadlineResult
    {
        return new DeadlineResult(DeadlineResolution::Ambiguous, null);
    }
}
