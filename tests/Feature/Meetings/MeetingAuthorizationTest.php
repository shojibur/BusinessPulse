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

test('a manager cannot create a meeting for another department, an owner only within the company', function () {
    $payload = ['title' => 'Huddle', 'meeting_date' => '2026-09-28'];

    $this->actingAs($this->f->manager)->post('/meetings', $payload + ['department_id' => $this->support->id])->assertNotFound();
    $this->actingAs($this->f->owner)->post('/meetings', $payload + ['department_id' => Department::factory()->create()->id])->assertSessionHasErrors('department_id');
    expect(Meeting::count())->toBe(1);

    $this->actingAs($this->f->manager)->post('/meetings', $payload)->assertRedirect(); // the Manager's own department by default
    expect(Meeting::latest('id')->first()->department_id)->toBe($this->f->sales->id)->and(Meeting::latest('id')->first()->status->value)->toBe('draft');
});
