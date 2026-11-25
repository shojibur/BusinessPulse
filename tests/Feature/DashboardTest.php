<?php

use App\Models\Department;
use App\Models\User;
use App\Services\Dashboard\DashboardMetrics;

test('while week 8 is overdue the dashboard shows the latest complete week with explicit dates and a warning', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    setClock('2026-09-28 09:00');

    // R2: the closed week 21-27 Sep is absent, so the latest closed COMPLETE week (14-20 Sep) is shown, never zero.
    $this->actingAs($owner)->get('/today')->assertInertia(fn ($page) => $page
        ->component('dashboard/Index')->where('audience', 'owner_manager')
        ->where('data.revenue.display', 'RM 155,000.00')->where('data.revenue.period_start', '2026-09-14')->where('data.revenue.period_end', '2026-09-20')
        ->where('data.change.value', '0.65')->where('data.targetAchievement.value', '99.36')
        ->where('data.coverageNotice', fn ($n) => str_contains($n, '21–27 Sep 2026') && str_contains($n, 'no sales data') && str_contains($n, '14–20 Sep 2026'))
        ->where('data.freshness.behind', true)
        ->where('data.freshness.message', fn ($m) => str_starts_with($m, 'Sales for 21–26 Sep not yet received: 18 branch-days overdue.'))
        ->where('data.openIssueCount', 0));
});

test('the dashboard shows the week 8 decline and shortfall from the engine', function () {
    $owner = declineTimeline();

    $this->actingAs($owner)->get('/today')->assertInertia(fn ($page) => $page
        ->where('data.revenue.display', 'RM 124,000.00')->where('data.change.value', '-20.00')->where('data.targetAchievement.value', '79.49')
        ->where('data.freshness.behind', false)->where('data.freshness.message', null)
        ->where('data.openIssueCount', 2)->where('data.highPriorityIssueCount', 1)
        ->where('data.evaluation.status', 'current')
        ->has('data.revenueVsTarget', 8)->where('data.revenueVsTarget.7.revenue', '124000.00'));
});

test('an incomplete latest week keeps the last complete week visible with a coverage warning', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    importFixture($owner, 'week8-with-errors', '2026-09-28 09:00');

    $this->actingAs($owner)->get('/today')->assertInertia(fn ($page) => $page
        ->where('data.revenue.available', true)->where('data.revenue.period_start', '2026-09-14')
        ->where('data.coverageNotice', fn ($n) => str_contains($n, 'is missing') && str_contains($n, 'latest complete week (14–20 Sep 2026)')));
});

test('staff receive no executive data', function () {
    $f = identity();

    $this->actingAs($f->staff)->get('/today')->assertInertia(fn ($page) => $page
        ->component('dashboard/Index')->where('audience', 'staff')->missing('data'));
});

test('a manager sees only their own department', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    $manager = User::where('email', 'aisyah@meridian.demo')->firstOrFail();

    $this->actingAs($manager)->get('/today')->assertInertia(fn ($page) => $page->where('data.departmentName', 'Sales'));
    expect(app(DashboardMetrics::class))->toBeInstanceOf(DashboardMetrics::class)->and(Department::count())->toBeGreaterThan(0);
});
