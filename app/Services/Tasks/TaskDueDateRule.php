<?php

namespace App\Services\Tasks;

use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** A due date is valid when it is not before the meeting date and not before the business date (today is valid). */
final class TaskDueDateRule
{
    public static function minimum(?CarbonInterface $meetingDate): CarbonImmutable
    {
        $today = CarbonImmutable::parse(app(BusinessClock::class)->today()->format('Y-m-d'));

        return $meetingDate !== null && $meetingDate->format('Y-m-d') > $today->format('Y-m-d')
            ? CarbonImmutable::parse($meetingDate->format('Y-m-d'))
            : $today;
    }

    /** @return array<string, string> field => message; empty when valid */
    public static function check(CarbonInterface $due, ?CarbonInterface $meetingDate): array
    {
        $dueDate = $due->format('Y-m-d');

        if ($meetingDate !== null && $dueDate < $meetingDate->format('Y-m-d')) {
            return ['due_date' => 'The deadline cannot be before the meeting date.'];
        }

        if ($dueDate < app(BusinessClock::class)->today()->format('Y-m-d')) {
            return ['due_date' => 'The deadline cannot be in the past.'];
        }

        return [];
    }
}
