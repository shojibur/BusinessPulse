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
