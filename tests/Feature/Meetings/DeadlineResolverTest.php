<?php

use App\Enums\DeadlineResolution;
use App\Services\Meetings\DeadlineResolver;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->company = meetingRoster()->company;
    $this->resolver = app(DeadlineResolver::class);
});

function resolveDeadline(?string $phrase, string $meetingDate = '2026-09-28'): array
{
    $r = test()->resolver->resolve($phrase, $meetingDate, test()->company);

    return [$r->resolution, $r->date?->format('Y-m-d')];
}

test('weekday phrases resolve from the meeting date', function () {
    expect(resolveDeadline('by Friday'))->toBe([DeadlineResolution::Resolved, '2026-10-02'])
        ->and(resolveDeadline('Friday'))->toBe([DeadlineResolution::Resolved, '2026-10-02'])
        ->and(resolveDeadline('Monday'))->toBe([DeadlineResolution::Resolved, '2026-09-28'])
        ->and(resolveDeadline('next Wednesday'))->toBe([DeadlineResolution::Resolved, '2026-10-07'])
        ->and(resolveDeadline('by next Wednesday'))->toBe([DeadlineResolution::Resolved, '2026-10-07']);
});

test('relative and end-of-week phrases resolve', function () {
    expect(resolveDeadline('tomorrow'))->toBe([DeadlineResolution::Resolved, '2026-09-29'])
        ->and(resolveDeadline('today'))->toBe([DeadlineResolution::Resolved, '2026-09-28'])
        ->and(resolveDeadline('end of the week'))->toBe([DeadlineResolution::Resolved, '2026-10-03'])
        ->and(resolveDeadline('by end of next week'))->toBe([DeadlineResolution::Resolved, '2026-10-10'])
        ->and(resolveDeadline('2026-10-09'))->toBe([DeadlineResolution::Resolved, '2026-10-09']);
});

test('unclear, impossible and blank phrases are never guessed', function () {
    expect(resolveDeadline('sometime soon'))->toBe([DeadlineResolution::Ambiguous, null])
        ->and(resolveDeadline('ASAP'))->toBe([DeadlineResolution::Ambiguous, null])
        ->and(resolveDeadline('next month'))->toBe([DeadlineResolution::Ambiguous, null])
        ->and(resolveDeadline('2026-02-30'))->toBe([DeadlineResolution::Ambiguous, null])
        ->and(resolveDeadline('2026-09-01'))->toBe([DeadlineResolution::Ambiguous, null]) // before the meeting
        ->and(resolveDeadline('   '))->toBe([DeadlineResolution::None, null])
        ->and(resolveDeadline(null))->toBe([DeadlineResolution::None, null]);
});

test('a Sunday meeting cannot resolve end of the week to an earlier Saturday', function () {
    expect(resolveDeadline('end of the week', '2026-10-04'))->toBe([DeadlineResolution::Ambiguous, null]);
});

test('relative dates use the meeting date, never the current system date', function () {
    // The same historical meeting resolves identically whatever "now" is.
    CarbonImmutable::setTestNow('2031-03-15 10:00:00');
    expect(resolveDeadline('by Friday', '2026-09-28'))->toBe([DeadlineResolution::Resolved, '2026-10-02']);
    CarbonImmutable::setTestNow('2026-09-30 10:00:00');
    expect(resolveDeadline('by Friday', '2026-09-28'))->toBe([DeadlineResolution::Resolved, '2026-10-02']);
    CarbonImmutable::setTestNow();
});
