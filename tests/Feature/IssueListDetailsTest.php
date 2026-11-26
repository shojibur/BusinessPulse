<?php

use App\Enums\RuleKey;
use App\Models\Outcome;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->f = meetingRoster();
    $this->issue = fakeIssue($this->f->sales);
});

test('the list rows carry a summary, figure chips, task progress and counts from real data', function () {
    makeTask($this->f, $this->f->priya, ['issue_id' => $this->issue->id, 'status' => 'completed']);
    makeTask($this->f, $this->f->farid, ['issue_id' => $this->issue->id, 'status' => 'completed']);
    makeTask($this->f, $this->f->farid, ['issue_id' => $this->issue->id, 'status' => 'pending']);
    makeTask($this->f, $this->f->farid, ['issue_id' => $this->issue->id, 'status' => 'cancelled']); // never counted

    $this->actingAs($this->f->owner)->get('/issues')->assertInertia(fn (Assert $p) => $p
        ->where('counts.open', 1)->where('counts.high', 1)->where('counts.in_action', 0)->where('counts.monitoring', 0)
        ->where('issues.0.rule_key', 'weekly_revenue_decline')
        ->where('issues.0.summary', 'Revenue RM 124,000.00, down 20.00% from RM 155,000.00')
        ->where('issues.0.chips.0', ['kind' => 'money', 'label' => 'RM 124,000.00'])
        ->where('issues.0.chips.1', ['kind' => 'down', 'label' => '-20.00%'])
        ->where('issues.0.tasks', ['total' => 3, 'completed' => 2])
        ->where('issues.0.latest_outcome', null)
        ->has('departments', 1));
});

test('search and department filters are applied safely and unknown values are ignored', function () {
    $target = fakeIssue($this->f->sales, key: RuleKey::SalesTargetShortfall);

    $titles = fn (string $query) => $this->actingAs($this->f->owner)->get('/issues?'.$query)->viewData('page')['props']['issues'];

    expect(collect($titles('q=target'))->pluck('id')->all())->toBe([$target->id])
        ->and($titles('q=%'))->toBe([]) // a percent sign is searched literally, never as a wildcard
        ->and($titles('department=999999'))->toHaveCount(2) // a department the user cannot see is ignored
        ->and($titles('department='.$this->f->sales->id))->toHaveCount(2);
});

test('the latest outcome appears in the list and drives the detail chart and eligibility', function () {
    Outcome::query()->forceCreate([
        'issue_id' => $this->issue->id, 'baseline_evaluation_id' => $this->issue->baseline_evaluation_id, 'followup_evaluation_id' => $this->issue->latest_evaluation_id,
        'phase' => 'post_action', 'outcome_metric' => 'weekly_revenue', 'baseline_value' => '124000.00', 'followup_value' => '150000.00', 'reference_value' => '155000.00',
        'absolute_change' => '26000.00', 'relative_change_pct' => '20.97', 'result' => 'improved', 'comparable' => true, 'resolution_condition_met' => true,
        'evaluated_at' => now(),
        'evidence' => [
            'phase' => ['value' => 'post_action', 'actionDate' => '2026-09-29', 'monitoringDate' => '2026-10-07'],
            'baseline' => ['periodStart' => '2026-09-21', 'periodEnd' => '2026-09-27', 'referencePeriod' => ['start' => '2026-09-14', 'end' => '2026-09-20'], 'thresholdUsed' => '15.00'],
            'followup' => ['periodStart' => '2026-10-05', 'periodEnd' => '2026-10-11'],
            'checks' => [['name' => 'baseline_sufficient', 'state' => 'passed'], ['name' => 'same_branches', 'state' => 'passed']],
            'condition' => ['type' => 'recovery', 'thresholdUsed' => '15.00', 'limit4' => '131750.0000', 'met' => true, 'valueMet' => true],
        ],
    ]);

    $this->actingAs($this->f->owner)->get('/issues')->assertInertia(fn (Assert $p) => $p
        ->where('issues.0.latest_outcome.result', 'improved')->where('issues.0.latest_outcome.label', 'Improved')->where('issues.0.latest_outcome.change', '+20.97%'));

    $this->actingAs($this->f->owner)->get("/issues/{$this->issue->id}")->assertInertia(fn (Assert $p) => $p
        ->where('chart.unit', 'MYR')->where('chart.values', ['155000.00', '124000.00', '150000.00'])->has('chart.categories', 3)
        ->where('eligibility.met', true)->where('eligibility.limitLabel', 'MYR 131,750.00')->where('eligibility.checks.0.label', 'Baseline week has enough data'));

    // Staff never see outcome data.
    $this->actingAs($this->f->priya)->get("/issues/{$this->issue->id}")->assertForbidden();
});

test('a detail page without an outcome has no chart or eligibility card', function () {
    $this->actingAs($this->f->owner)->get("/issues/{$this->issue->id}")->assertInertia(fn (Assert $p) => $p
        ->where('chart', null)->where('eligibility', null)->where('issue.rule_key', 'weekly_revenue_decline'));
});
