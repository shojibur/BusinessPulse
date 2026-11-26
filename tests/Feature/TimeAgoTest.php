<?php

use App\Models\Brief;
use App\Support\TimeAgo;
use Carbon\CarbonImmutable;

beforeEach(fn () => setClock('2026-09-28 09:00')); // the business clock, not the real one

test('moments are described relative to the business clock in plain words', function (string $time, string $expected) {
    expect(app(TimeAgo::class)->label(CarbonImmutable::parse($time, 'Asia/Kuala_Lumpur')))->toBe($expected);
})->with([
    'seconds ago' => ['2026-09-28 08:59:40', 'just now'],
    'minutes' => ['2026-09-28 08:55:00', '5 minutes ago'],
    'one hour' => ['2026-09-28 08:00:00', '1 hour ago'],
    'hours' => ['2026-09-28 04:00:00', '5 hours ago'],
    'one day' => ['2026-09-27 08:00:00', '1 day ago'],
    'days' => ['2026-09-25 09:00:00', '3 days ago'],
    'weeks' => ['2026-09-07 09:00:00', '3 weeks ago'],
]);

test('nothing is shown for a missing time', function () {
    expect(app(TimeAgo::class)->label(null))->toBeNull();
});

test('the brief pages and the dashboard carry the readable age', function () {
    $owner = declineTimeline();
    $this->actingAs($owner)->post('/briefs');

    $this->actingAs($owner)->get('/briefs')->assertInertia(fn ($p) => $p->where('briefs.0.ago', 'just now'));
    $this->actingAs($owner)->get('/briefs/'.Brief::sole()->id)->assertInertia(fn ($p) => $p->where('brief.ago', 'just now'));
    $this->actingAs($owner)->get('/today')->assertInertia(fn ($p) => $p->where('data.latestBrief.ago', 'just now')->where('data.activity.0.ago', fn ($a) => is_string($a) && $a !== ''));
});
