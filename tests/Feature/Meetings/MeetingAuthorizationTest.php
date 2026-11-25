<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\User;

beforeEach(function () {
    $this->f = meetingRoster();
    $this->meeting = makeMeeting($this->f);
    $this->support = Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'support', 'name' => 'Support']);
    $this->supportManager = User::factory()->manager($this->support)->create(['name' => 'Mona Manager']);
    $this->otherOwner = User::factory()->owner()->create(['company_id' => Company::factory()->create()->id]);
});

test('staff get 403 on every meeting route', function () {
    $id = $this->meeting->id;
    foreach (['get' => ['/meetings', '/meetings/create', "/meetings/{$id}", "/meetings/{$id}/edit", "/meetings/{$id}/recording/stream", "/meetings/{$id}/recording/download"],
        'post' => ['/meetings', "/meetings/{$id}/recording", "/meetings/{$id}/transcript", "/meetings/{$id}/retry"],
        'put' => ["/meetings/{$id}", "/meetings/{$id}/transcript"]] as $method => $urls) {
        foreach ($urls as $url) {
            $this->actingAs($this->f->priya)->{$method}($url)->assertForbidden();
        }
    }
});

test('owner and own-department manager can open a meeting, other scopes get 404', function () {
    $this->actingAs($this->f->owner)->get("/meetings/{$this->meeting->id}")->assertOk();
    $this->actingAs($this->f->manager)->get("/meetings/{$this->meeting->id}")->assertOk();
    $this->actingAs($this->supportManager)->get("/meetings/{$this->meeting->id}")->assertNotFound();
    $this->actingAs($this->supportManager)->get("/meetings/{$this->meeting->id}/edit")->assertNotFound();
    $this->actingAs($this->supportManager)->post("/meetings/{$this->meeting->id}/transcript", ['transcript' => demoTranscript()])->assertNotFound();
    $this->actingAs($this->supportManager)->post("/meetings/{$this->meeting->id}/retry")->assertNotFound();
    $this->actingAs($this->otherOwner)->get("/meetings/{$this->meeting->id}")->assertNotFound();
    auth()->logout();
    $this->get("/meetings/{$this->meeting->id}")->assertRedirect('/login');
});

test('the list is scoped: managers see their department, the owner the company, nobody else', function () {
    $supportMeeting = Meeting::create(['department_id' => $this->support->id, 'created_by' => $this->supportManager->id, 'title' => 'Support sync', 'meeting_date' => '2026-09-29']);

    $this->actingAs($this->f->manager)->get('/meetings')->assertInertia(fn ($p) => $p->has('meetings', 1)->where('meetings.0.id', $this->meeting->id));
    $this->actingAs($this->supportManager)->get('/meetings')->assertInertia(fn ($p) => $p->has('meetings', 1)->where('meetings.0.id', $supportMeeting->id));
    $this->actingAs($this->f->owner)->get('/meetings')->assertInertia(fn ($p) => $p->has('meetings', 2));
    $this->actingAs($this->otherOwner)->get('/meetings')->assertInertia(fn ($p) => $p->has('meetings', 0));
});

test('a manager cannot create a meeting for another department, an owner only within the company', function () {
    $payload = ['title' => 'Huddle', 'meeting_date' => '2026-09-28'];

    $this->actingAs($this->f->manager)->post('/meetings', $payload + ['department_id' => $this->support->id])->assertNotFound();
    $this->actingAs($this->f->owner)->post('/meetings', $payload + ['department_id' => Department::factory()->create()->id])->assertSessionHasErrors('department_id');
    expect(Meeting::count())->toBe(1);

    $this->actingAs($this->f->manager)->post('/meetings', $payload)->assertRedirect(); // the Manager's own department by default
    expect(Meeting::latest('id')->first()->department_id)->toBe($this->f->sales->id)->and(Meeting::latest('id')->first()->status->value)->toBe('draft');
});

test('linked issues must belong to the meeting department and are limited to ten', function () {
    $salesIssue = fakeIssue($this->f->sales);
    $supportIssue = fakeIssue($this->support);
    $payload = ['title' => 'Huddle', 'meeting_date' => '2026-09-28', 'department_id' => $this->f->sales->id];

    $this->actingAs($this->f->owner)->post('/meetings', $payload + ['issue_ids' => [$supportIssue->id]])->assertSessionHasErrors('issue_ids.0');
    $this->actingAs($this->f->owner)->post('/meetings', $payload + ['issue_ids' => range(1, 11)])->assertSessionHasErrors('issue_ids');
    $this->actingAs($this->f->owner)->post('/meetings', $payload + ['issue_ids' => [$salesIssue->id]])->assertRedirect();
    expect(Meeting::latest('id')->first()->issues()->pluck('issues.id')->all())->toBe([$salesIssue->id]);
});

test('the department of a meeting cannot be changed and the date is locked while processing', function () {
    $this->actingAs($this->f->manager)->put("/meetings/{$this->meeting->id}", ['title' => 'Renamed', 'meeting_date' => '2026-09-28', 'department_id' => $this->support->id])->assertRedirect();
    expect($this->meeting->fresh()->department_id)->toBe($this->f->sales->id)->and($this->meeting->fresh()->title)->toBe('Renamed');

    $this->meeting->update(['status' => 'extracting']);
    $this->actingAs($this->f->manager)->put("/meetings/{$this->meeting->id}", ['title' => 'Renamed', 'meeting_date' => '2026-10-05'])->assertSessionHasErrors('meeting_date');
});

test('the issue page shows meetings in scope only and nothing for staff', function () {
    $issue = fakeIssue($this->f->sales);
    $this->meeting->issues()->attach($issue->id);

    $this->actingAs($this->f->manager)->get("/issues/{$issue->id}")->assertInertia(fn ($p) => $p->has('meetings.items', 1)->where('meetings.items.0.id', $this->meeting->id)->missing('meetings.items.0.transcript'));
    $this->actingAs($this->f->owner)->get("/issues/{$issue->id}")->assertInertia(fn ($p) => $p->where('meetings.createUrl', fn ($u) => str_contains($u, 'issue='.$issue->id)));
    $this->actingAs($this->f->priya)->get("/issues/{$issue->id}")->assertForbidden();
});
