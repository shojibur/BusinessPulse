<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    $this->f = meetingRoster();
    $this->x = reviewFixture($this->f); // an issue and four pending task drafts
    $this->issueId = $this->x->issue->id;

    makeTask($this->f, $this->f->priya, ['title' => 'Call Penang accounts', 'issue_id' => $this->issueId, 'description' => "  Re-engage   top\naccounts  "]);
    makeTask($this->f, $this->f->farid, ['title' => 'Prepare JB promotion', 'status' => 'pending']);
    makeTask($this->f, $this->f->priya, ['title' => 'Update contact list', 'status' => 'in_progress']);
    makeTask($this->f, $this->f->farid, ['title' => 'Confirm promotion budget', 'status' => 'blocked']);
    makeTask($this->f, $this->f->farid, ['title' => 'Prepare campaign brief', 'status' => 'completed']);
    makeTask($this->f, $this->f->farid, ['title' => 'Old idea', 'status' => 'cancelled']);
});

test('the management page opens on the board with the four status columns, real counts and the stats', function () {
    $this->actingAs($this->f->owner)->get('/tasks')->assertOk()->assertInertia(fn ($p) => $p
        ->component('tasks/Index')->where('view', 'board')->where('tasks', null)->where('pagination', null)
        ->where('board.columns.0.status', 'pending')->where('board.columns.0.count', 2)
        ->where('board.columns.1.status', 'in_progress')->where('board.columns.1.count', 1)
        ->where('board.columns.2.status', 'blocked')->where('board.columns.2.count', 1)
        ->where('board.columns.3.status', 'completed')->where('board.columns.3.count', 1)
        ->where('board.shown', 5)->where('board.total', 5)->where('board.cancelled', 1)
        ->where('stats.total', 6)->where('stats.inProgress', 1)->where('stats.completed', 1)->where('stats.pendingDrafts', 4));
});

test('the table view is paginated and carries a one-line description', function () {
    $this->actingAs($this->f->owner)->get('/tasks?view=list')->assertOk()->assertInertia(fn ($p) => $p
        ->where('view', 'list')->where('board', null)->has('tasks', 6)->where('pagination.total', 6)
        ->where('tasks', fn ($tasks) => collect($tasks)->contains(fn ($t) => $t['title'] === 'Call Penang accounts' && $t['description'] === 'Re-engage top accounts')));
});

test('issue, employee, status and search filters narrow both views', function () {
    $this->actingAs($this->f->owner)->get("/tasks?view=list&issue={$this->issueId}")->assertInertia(fn ($p) => $p->has('tasks', 1)->where('tasks.0.title', 'Call Penang accounts')->where('filters.issue', $this->issueId));
    $this->actingAs($this->f->owner)->get('/tasks?view=list&q=promotion')->assertInertia(fn ($p) => $p->has('tasks', 2)->where('filters.q', 'promotion'));
    $this->actingAs($this->f->owner)->get("/tasks?assignee={$this->f->priya->id}")->assertInertia(fn ($p) => $p->where('board.total', 2));
    $this->actingAs($this->f->owner)->get('/tasks?status=blocked')->assertInertia(fn ($p) => $p->where('board.columns.2.count', 1)->where('board.columns.0.count', 0)->where('board.total', 1));
    // Wildcard characters in the search are plain text.
    $this->actingAs($this->f->owner)->get('/tasks?view=list&q=%25')->assertInertia(fn ($p) => $p->has('tasks', 0));
});

test('a manager of another department sees an empty board, and staff are forbidden', function () {
    $support = Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'support', 'name' => 'Support']);
    $otherManager = User::factory()->manager($support)->create();
    $otherOwner = User::factory()->owner()->create(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($otherManager)->get('/tasks')->assertInertia(fn ($p) => $p->where('board.total', 0)->where('stats.total', 0)->where('stats.pendingDrafts', 0)->where('options.issues', []));
    $this->actingAs($otherOwner)->get('/tasks')->assertInertia(fn ($p) => $p->where('board.total', 0)->where('stats.total', 0));
    $this->actingAs($this->f->priya)->get('/tasks')->assertForbidden();
});
