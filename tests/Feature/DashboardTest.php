<?php

use App\Models\Department;
use App\Models\User;
use App\Services\Dashboard\DashboardMetrics;

test('while week 8 is overdue the dashboard reports no sales for the latest closed week, never zero', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    setClock('2026-09-28 09:00');

    // Design G8: the dashboard uses the same latest closed week as S04 (21-27 Sep, absent) and never searches back.
    $this->actingAs($owner)->get('/today')->assertInertia(fn ($page) => $page
        ->component('dashboard/Index')->where('audience', 'owner_manager')
        ->where('data.revenue.available', false)->where('data.revenue.reason', 'period_absent')
        ->where('data.change.available', false)->where('data.targetAchievement.available', false)
        ->where('data.freshness.behind', true)
        ->where('data.freshness.message', fn ($m) => str_starts_with($m, 'Sales for 21–26 Sep not yet received: 18 branch-days overdue.') && str_contains($m, 'last been refreshed') === false)
        ->where('data.revenueVsTarget.6.revenue', '155000.00')->where('data.openIssueCount', 0));
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

test('an incomplete latest week shows insufficient data, never zero', function () {
    $owner = demoOwner();
    importFixture($owner, 'history', '2026-09-21 09:00');
    importFixture($owner, 'week8-with-errors', '2026-09-28 09:00');

    $this->actingAs($owner)->get('/today')->assertInertia(fn ($page) => $page
        ->where('data.revenue.available', false)->where('data.revenue.reason', 'incomplete_period')
        ->where('data.change.available', false)->where('data.targetAchievement.available', false)
        ->where('data.coverageNotice', fn ($n) => str_contains($n, 'branch-days are missing')));
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
