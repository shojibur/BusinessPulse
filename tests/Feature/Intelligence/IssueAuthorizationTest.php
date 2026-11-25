<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\RuleKey;
use App\Jobs\EvaluateRulesJob;
use App\Models\BusinessRule;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Notifications\UrgentIssueNotification;
use App\Services\Intelligence\IssueLifecycle;
use Illuminate\Notifications\DatabaseNotification;
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

test('guests and inactive users are sent to the login page', function () {
    $this->get('/issues')->assertRedirect(route('login'));
    $this->postJson("/issues/{$this->salesIssue->id}/resolve")->assertUnauthorized();

    $this->f->owner->forceFill(['is_active' => false])->save();
    $this->actingAs($this->f->owner)->get('/issues')->assertRedirect(route('login'));
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

test('the detail page offers only the actions valid for the status', function () {
    $this->actingAs($this->f->owner);
    $actions = fn (IssueStatus $status) => (function () use ($status) {
        Issue::whereKey($this->salesIssue->id)->update(['status' => $status->value, 'resolved_at' => $status === IssueStatus::Resolved ? now() : null,
            'resolution_type' => $status === IssueStatus::Resolved ? 'manual' : null, 'resolution_note' => $status === IssueStatus::Resolved ? 'x' : null]);

        return $this->get("/issues/{$this->salesIssue->id}")->viewData('page')['props']['actions'];
    })();

    expect($actions(IssueStatus::Open))->toBe(['acknowledge' => true, 'monitoring' => false, 'resolve' => true])
        ->and($actions(IssueStatus::Acknowledged))->toBe(['acknowledge' => false, 'monitoring' => false, 'resolve' => true])
        ->and($actions(IssueStatus::ActionInProgress))->toBe(['acknowledge' => false, 'monitoring' => true, 'resolve' => true])
        ->and($actions(IssueStatus::Monitoring))->toBe(['acknowledge' => false, 'monitoring' => false, 'resolve' => true])
        ->and($actions(IssueStatus::Resolved))->toBe(['acknowledge' => false, 'monitoring' => false, 'resolve' => false]);
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

test('the issue list filters, sorts high first and paginates by twenty', function () {
    // A department has at most one open issue per rule, so pagination is exercised with resolved history.
    foreach (range(1, 25) as $i) {
        fakeIssue($this->f->sales, IssueStatus::Resolved, severityFor($i));
    }

    $this->actingAs($this->f->owner);
    $props = fn (string $query = '') => $this->get('/issues'.$query)->viewData('page')['props'];

    expect($props()['pagination']['total'])->toBe(2) // default: the two open issues
        ->and($props('?status=all')['issues'])->toHaveCount(20)
        ->and($props('?status=all')['pagination']['total'])->toBe(27)
        ->and($props('?status=all')['pagination']['last_page'])->toBe(2)
        ->and($props('?status=all')['issues'][0]['severity'])->toBe('high')
        ->and(collect($props('?status=all&severity=low')['issues'])->pluck('severity')->unique()->all())->toBe(['low'])
        ->and($props('?status=resolved')['pagination']['total'])->toBe(25)
        ->and($props('?status=open')['pagination']['total'])->toBe(2);

    // Severity order: every high before every medium before every low.
    $order = collect($props('?status=all')['issues'])->pluck('severity')->map(fn ($s) => ['high' => 0, 'medium' => 1, 'low' => 2][$s])->all();
    $sorted = $order;
    sort($sorted);
    expect($order)->toBe($sorted);
});

test('the list loads with a bounded number of queries whatever the number of issues', function () {
    $this->actingAs($this->f->owner);
    $queries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/issues?status=all');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $queries(); // warm up
    $few = $queries();
    foreach (range(1, 15) as $i) {
        fakeIssue($this->f->sales, IssueStatus::Resolved, severityFor($i), RuleKey::from(['weekly_revenue_decline', 'sales_target_shortfall', 'missing_sales_data'][$i % 3]));
    }

    expect($queries())->toBe($few);
});

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

test('Issue, rule and evaluation records are scoped through their department', function () {
    $ids = fn ($user) => Issue::visibleTo($user)->pluck('id')->sort()->values()->all();

    expect($ids($this->f->owner))->toBe([$this->salesIssue->id, $this->supportIssue->id])
        ->and($ids($this->f->manager))->toBe([$this->salesIssue->id])
        ->and($ids($this->f->inactive))->toBe([])
        ->and(RuleEvaluation::visibleTo($this->f->manager)->pluck('department_id')->unique()->all())->toBe([$this->f->sales->id])
        ->and(BusinessRule::visibleTo($this->f->otherManager)->pluck('department_id')->unique()->all())->toBe([$this->f->support->id]);
});

test('notifications: each user sees and clears only their own', function () {
    $mk = fn ($user) => $user->notify(new UrgentIssueNotification([
        'dedupe_key' => "{$this->salesIssue->id}:high:{$user->id}", 'issue_id' => $this->salesIssue->id, 'title' => 'Weekly revenue decline',
        'severity' => 'high', 'department_name' => 'Sales', 'summary_line' => 'Revenue fell', 'url' => "/issues/{$this->salesIssue->id}",
    ]));
    $mk($this->f->owner);
    $mk($this->f->manager);
    $ownerNotification = DatabaseNotification::where('notifiable_id', $this->f->owner->id)->first();
    $managerNotification = DatabaseNotification::where('notifiable_id', $this->f->manager->id)->first();

    $this->actingAs($this->f->manager)->get('/notifications')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('notifications/Index')->has('notifications', 1)->where('unreadCount', 1));

    // Another user's notification is a 404 and stays unread.
    $this->post("/notifications/{$ownerNotification->id}/read")->assertNotFound();
    expect($ownerNotification->fresh()->read_at)->toBeNull();

    $this->post("/notifications/{$managerNotification->id}/read")->assertRedirect();
    expect($managerNotification->fresh()->read_at)->not->toBeNull()->and($ownerNotification->fresh()->read_at)->toBeNull();

    $mk($this->f->manager);
    $this->post('/notifications/read-all')->assertRedirect();
    expect(DatabaseNotification::where('notifiable_id', $this->f->manager->id)->whereNull('read_at')->count())->toBe(0)
        ->and($ownerNotification->fresh()->read_at)->toBeNull();
});

test('every role can reach its own notifications and the bell data is shared', function () {
    $this->f->staff->notify(new UrgentIssueNotification([
        'dedupe_key' => 'x:1', 'issue_id' => $this->salesIssue->id, 'title' => 'Weekly revenue decline', 'severity' => 'high',
        'department_name' => 'Sales', 'summary_line' => 'Revenue fell', 'url' => "/issues/{$this->salesIssue->id}",
    ]));

    // Staff cannot open the issue, so the item is shown without a link.
    $this->actingAs($this->f->staff)->get('/today')->assertInertia(fn (Assert $page) => $page
        ->where('notifications.unread_count', 1)->where('notifications.items.0.url', null)->where('notifications.items.0.title', 'Weekly revenue decline'));
    $this->get('/notifications')->assertOk();

    $this->actingAs($this->f->manager)->get('/today')->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0));
    $this->post('/logout');
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('notifications', null));
});

test('evidence values are rendered as escaped text only', function () {
    foreach (['v-html', 'innerHTML'] as $needle) {
        foreach (['pages/issues/Show.vue', 'pages/issues/Index.vue', 'components/EvaluationEvidence.vue', 'pages/notifications/Index.vue'] as $file) {
            expect(file_get_contents(resource_path("js/{$file}")))->not->toContain($needle);
        }
    }
});
