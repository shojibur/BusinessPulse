<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Jobs\EvaluateRulesJob;
use App\Models\BusinessRule;
use App\Models\RuleEvaluation;
use App\Services\Intelligence\IssueLifecycle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->f = identity();
    setClock('2026-09-28 09:00');
    $this->salesIssue = fakeIssue($this->f->sales);
    $this->supportIssue = fakeIssue($this->f->support);
});

test('Staff get 403 on every issue route and never receive issue data', function () {
    $this->actingAs($this->f->staff);
    $id = $this->salesIssue->id;

    foreach ([['GET', '/issues'], ['GET', "/issues/{$id}"], ['POST', '/issues/refresh'], ['POST', "/issues/{$id}/acknowledge"],
        ['POST', "/issues/{$id}/monitoring"], ['POST', "/issues/{$id}/resolve"]] as [$method, $path]) {
        $this->call($method, $path)->assertForbidden();
    }

    expect($this->salesIssue->fresh()->status)->toBe(IssueStatus::Open);
    expect($this->get('/today')->getContent())->not->toContain('Weekly revenue decline');
});

test('the Owner sees the issues of the whole company and can open each', function () {
    $this->actingAs($this->f->owner);

    $this->get('/issues')->assertOk()->assertInertia(fn (Assert $page) => $page->component('issues/Index')->has('issues', 2));
    $this->get("/issues/{$this->salesIssue->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('issues/Show'));
    $this->get("/issues/{$this->supportIssue->id}")->assertOk();
});

test('a Manager sees only their department: another department is a 404 for every route', function () {
    $this->actingAs($this->f->manager);

    $this->get('/issues')->assertOk()->assertInertia(fn (Assert $page) => $page->has('issues', 1)->where('issues.0.id', $this->salesIssue->id));
    $this->get("/issues/{$this->salesIssue->id}")->assertOk();

    foreach ([['GET', ''], ['POST', '/acknowledge'], ['POST', '/monitoring'], ['POST', '/resolve']] as [$method, $suffix]) {
        $this->call($method, "/issues/{$this->supportIssue->id}{$suffix}")->assertNotFound();
    }

    $this->get('/issues/999999')->assertNotFound();
    expect($this->supportIssue->fresh()->status)->toBe(IssueStatus::Open);

    $this->actingAs($this->f->otherManager)->get("/issues/{$this->salesIssue->id}")->assertNotFound();
});

test('an in-scope Manager can acknowledge, start monitoring and resolve through the endpoints', function () {
    $this->actingAs($this->f->manager);

    $this->post("/issues/{$this->salesIssue->id}/acknowledge")->assertRedirect();
    expect($this->salesIssue->fresh()->status)->toBe(IssueStatus::Acknowledged);

    app(IssueLifecycle::class)->startAction($this->salesIssue, null);
    $this->post("/issues/{$this->salesIssue->id}/monitoring", ['reason' => 'Tasks completed, watching a week.'])->assertRedirect();
    expect($this->salesIssue->fresh()->status)->toBe(IssueStatus::Monitoring);

    $this->post("/issues/{$this->salesIssue->id}/resolve", ['reason' => 'Confirmed with the branch.'])->assertRedirect();
    $issue = $this->salesIssue->fresh();
    expect($issue->status)->toBe(IssueStatus::Resolved)->and($issue->resolved_by)->toBe($this->f->manager->id)
        ->and($issue->resolution_note)->toBe('Confirmed with the branch.');
});

test('missing reasons and forbidden transitions are 422 and change nothing; a double submit is a 200 no-op', function () {
    $this->actingAs($this->f->owner);
    $id = $this->salesIssue->id;

    $this->postJson("/issues/{$id}/resolve", ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson("/issues/{$id}/resolve", ['reason' => str_repeat('x', 2001)])->assertUnprocessable();
    $this->postJson("/issues/{$id}/monitoring", ['reason' => 'ok'])->assertUnprocessable(); // open -> monitoring is not allowed
    expect($this->salesIssue->fresh()->status)->toBe(IssueStatus::Open);

    $this->postJson("/issues/{$id}/acknowledge")->assertOk()->assertJson(['status' => 'acknowledged', 'changed' => true]);
    $this->postJson("/issues/{$id}/acknowledge")->assertOk()->assertJson(['status' => 'acknowledged', 'changed' => false]);
    expect($this->salesIssue->fresh()->status_history)->toHaveCount(2);

    $this->postJson("/issues/{$id}/resolve", ['reason' => 'Done.'])->assertOk();
    $this->postJson("/issues/{$id}/acknowledge")->assertUnprocessable(); // resolved issues cannot move
});

test('Refresh intelligence queues one department job for the Owner and the in-scope Manager only', function () {
    Queue::fake();

    $this->actingAs($this->f->manager)->post('/issues/refresh')->assertRedirect();
    Queue::assertPushed(EvaluateRulesJob::class, fn ($job) => $job->departmentId === $this->f->sales->id && $job->importId === null && $job->actorUserId === $this->f->manager->id);

    // Another department is a 404 for the Manager, even by id.
    $this->post('/issues/refresh', ['department_id' => $this->f->support->id])->assertNotFound();
    Queue::assertPushedTimes(EvaluateRulesJob::class, 1);

    $this->actingAs($this->f->owner)->post('/issues/refresh', ['department_id' => $this->f->support->id])->assertRedirect();
    Queue::assertPushed(EvaluateRulesJob::class, fn ($job) => $job->departmentId === $this->f->support->id);

    $this->actingAs($this->f->staff)->post('/issues/refresh')->assertForbidden();
});

test('Refresh intelligence is throttled to six requests per minute per user', function () {
    Queue::fake();
    $this->actingAs($this->f->owner);

    foreach (range(1, 6) as $ignored) {
        $this->post('/issues/refresh')->assertRedirect();
    }

    $this->post('/issues/refresh')->assertStatus(429);
    $this->actingAs($this->f->manager)->post('/issues/refresh')->assertRedirect(); // its own allowance
});

function severityFor(int $i): IssueSeverity
{
    return match ($i % 3) {
        0 => IssueSeverity::Low,
        1 => IssueSeverity::High,
        default => IssueSeverity::Medium,
    };
}

test('policies follow the department and deny Staff and inactive users', function () {
    foreach (['view', 'transition'] as $ability) {
        expect(Gate::forUser($this->f->owner)->allows($ability, $this->supportIssue))->toBeTrue()
            ->and(Gate::forUser($this->f->manager)->allows($ability, $this->salesIssue))->toBeTrue()
            ->and(Gate::forUser($this->f->manager)->denies($ability, $this->supportIssue))->toBeTrue()
            ->and(Gate::forUser($this->f->staff)->denies($ability, $this->salesIssue))->toBeTrue()
            ->and(Gate::forUser($this->f->inactive)->denies($ability, $this->salesIssue))->toBeTrue();
    }

    $evaluation = RuleEvaluation::first();
    $rule = BusinessRule::first();
    expect(Gate::forUser($this->f->staff)->denies('view', $evaluation))->toBeTrue()->and(Gate::forUser($this->f->staff)->denies('view', $rule))->toBeTrue()
        ->and(Gate::forUser($this->f->manager)->allows('view', $evaluation))->toBeTrue()
        ->and(Gate::forUser($this->f->otherManager)->denies('view', $evaluation))->toBeTrue();

    foreach (['create', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($this->f->owner)->denies($ability, $evaluation))->toBeTrue();
    }
});
