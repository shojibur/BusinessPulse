<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->f = meetingRoster();
    $this->x = reviewFixture($this->f);
    $this->mine = makeTask($this->f, $this->f->priya);
    $this->others = makeTask($this->f, $this->f->farid, ['title' => 'Private task of Farid']);
    $this->support = Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'support', 'name' => 'Support']);
    $this->supportManager = User::factory()->manager($this->support)->create();
    $this->otherOwner = User::factory()->owner()->create(['company_id' => Company::factory()->create()->id]);
});

test('staff see only their own tasks; another employee task is a 404 identical to a missing id', function () {
    $this->actingAs($this->f->priya)->get("/tasks/{$this->mine->id}")->assertOk();

    $other = $this->actingAs($this->f->priya)->get("/tasks/{$this->others->id}");
    $missing = $this->actingAs($this->f->priya)->get('/tasks/9999999');
    $other->assertNotFound();
    $missing->assertNotFound();
    expect($other->viewData('page')['component'])->toBe($missing->viewData('page')['component'])->and($other->viewData('page')['props']['status'] ?? null)->toBe($missing->viewData('page')['props']['status'] ?? null);

    $this->actingAs($this->f->priya)->post("/tasks/{$this->others->id}/status", ['status' => 'completed'])->assertNotFound();
    $this->actingAs($this->f->priya)->post("/tasks/{$this->others->id}/comments", ['body' => 'hi'])->assertNotFound();
    expect($this->others->fresh()->status->value)->toBe('pending');
});

test('the management list is forbidden to staff; my tasks lists only their own', function () {
    $this->actingAs($this->f->priya)->get('/tasks')->assertForbidden();
    $this->actingAs($this->f->priya)->get('/my-tasks')->assertOk()->assertInertia(fn ($p) => $p->component('tasks/Mine')
        ->where('sections.open.0.id', $this->mine->id)->has('sections.open', 1));
    expect(json_encode($this->actingAs($this->f->priya)->get('/my-tasks')->viewData('page')['props']))->not->toContain('Private task of Farid');
});

test('staff cannot reach draft routes, meetings, issues or briefs', function () {
    $draft = $this->x->drafts[0];

    foreach ([['post', "/task-drafts/{$draft->id}/approve"], ['patch', "/task-drafts/{$draft->id}"], ['post', "/task-drafts/{$draft->id}/reject"], ['get', "/meetings/{$this->x->meeting->id}"], ['get', "/issues/{$this->x->issue->id}"], ['get', '/briefs']] as [$method, $url]) {
        $this->actingAs($this->f->priya)->{$method}($url)->assertForbidden();
    }
    expect(Task::count())->toBe(2);
});

test('a manager of another department gets 404 on tasks and drafts of this department', function () {
    $draft = $this->x->drafts[0];

    $this->actingAs($this->supportManager)->get("/tasks/{$this->mine->id}")->assertNotFound();
    $this->actingAs($this->supportManager)->post("/tasks/{$this->mine->id}/status", ['status' => 'in_progress'])->assertNotFound();
    $this->actingAs($this->supportManager)->post("/tasks/{$this->mine->id}/comments", ['body' => 'x'])->assertNotFound();
    foreach ([['post', "/task-drafts/{$draft->id}/approve"], ['patch', "/task-drafts/{$draft->id}"], ['post', "/task-drafts/{$draft->id}/reject"]] as [$method, $url]) {
        $this->actingAs($this->supportManager)->{$method}($url)->assertNotFound();
    }
    $this->actingAs($this->supportManager)->get("/meetings/{$this->x->meeting->id}")->assertNotFound();
    $this->actingAs($this->supportManager)->get('/tasks')->assertOk()->assertInertia(fn ($p) => $p->has('tasks', 0));
    $this->actingAs($this->otherOwner)->get("/tasks/{$this->mine->id}")->assertNotFound();
    expect(Task::count())->toBe(2)->and($draft->fresh()->status->value)->toBe('pending');
});

test('owner and own-department manager can read, change status, comment and review drafts', function () {
    $this->actingAs($this->f->owner)->get("/tasks/{$this->mine->id}")->assertOk();
    $this->actingAs($this->f->manager)->get("/tasks/{$this->mine->id}")->assertOk();
    $this->actingAs($this->f->manager)->get('/tasks')->assertOk()->assertInertia(fn ($p) => $p->has('tasks', 2));
    $this->actingAs($this->f->owner)->get('/tasks')->assertOk()->assertInertia(fn ($p) => $p->has('tasks', 2));
    $this->actingAs($this->f->manager)->post("/task-drafts/{$this->x->drafts[0]->id}/approve")->assertRedirect();
    $this->actingAs($this->f->manager)->get("/meetings/{$this->x->meeting->id}")->assertInertia(fn ($p) => $p->has('draftReview.pending', 3)->has('draftReview.history', 1));
});

test('staff task detail exposes no meeting link, decisions, evidence or issue link', function () {
    $task = makeTask($this->f, $this->f->priya, ['meeting_id' => $this->x->meeting->id, 'issue_id' => $this->x->issue->id]);
    $this->x->meeting->update(['decisions' => [['summary' => 'Confidential decision', 'excerpt' => 'We will prioritise reactivating the top lapsed accounts.']]]);

    $page = $this->actingAs($this->f->priya)->get("/tasks/{$task->id}");
    $props = $page->viewData('page')['props'];

    $page->assertOk()->assertInertia(fn ($p) => $p->where('meeting.url', null)->where('decisions', null)->where('issue.url', null)->where('issue.evidence', null)->where('issue.severity', null));
    expect(json_encode($props))->not->toContain('Confidential decision')->not->toContain('recording')->not->toContain('baseline');

    // Management gets the context and the limited evidence snapshot.
    $this->actingAs($this->f->manager)->get("/tasks/{$task->id}")->assertInertia(fn ($p) => $p->where('meeting.url', fn ($u) => str_contains($u, '/meetings/'))
        ->has('decisions', 1)->where('issue.url', fn ($u) => str_contains($u, '/issues/'))->where('issue.evidence.metricName', fn ($m) => is_string($m)));
});

test('the task list filters by status, employee and overdue, scoped and sorted overdue first', function () {
    setClock('2026-10-05 09:00');
    $overdue = makeTask($this->f, $this->f->priya, ['title' => 'Late one', 'due_date' => '2026-10-01']);
    makeTask($this->f, $this->f->farid, ['title' => 'Done one', 'status' => 'completed', 'due_date' => '2026-09-30']);
    makeTask($this->f, $this->f->farid, ['title' => 'Due today', 'due_date' => '2026-10-05']);

    $this->actingAs($this->f->owner)->get('/tasks')->assertInertia(fn ($p) => $p->where('tasks.0.id', $overdue->id)->where('tasks.0.isOverdue', true));
    $this->actingAs($this->f->owner)->get('/tasks?overdue=1')->assertInertia(fn ($p) => $p->has('tasks', 3)->where('tasks.0.id', $overdue->id));
    $this->actingAs($this->f->owner)->get('/tasks?status=completed')->assertInertia(fn ($p) => $p->has('tasks', 1)->where('tasks.0.title', 'Done one')->where('tasks.0.isOverdue', false));
    $this->actingAs($this->f->owner)->get('/tasks?assignee='.$this->f->farid->id)->assertInertia(fn ($p) => $p->has('tasks', 3));
    $this->actingAs($this->f->owner)->get('/tasks?assignee=999999')->assertInertia(fn ($p) => $p->has('tasks', 0));
});

test('the review actions share one throttle of 20 per minute', function () {
    $draft = $this->x->drafts[3];
    for ($i = 0; $i < 20; $i++) {
        $this->actingAs($this->f->owner)->patch("/task-drafts/{$draft->id}", ['title' => 'T'.$i]);
    }
    $this->actingAs($this->f->owner)->post("/task-drafts/{$draft->id}/reject")->assertStatus(429)->assertHeader('Retry-After');
});
