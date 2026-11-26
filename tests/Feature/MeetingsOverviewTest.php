<?php

use App\Enums\BriefStatus;
use App\Models\Brief;
use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    $this->f = meetingRoster();
    $this->brief = fn (array $attributes = []) => Brief::query()->create($attributes + [
        'company_id' => $this->f->company->id, 'department_id' => null, 'requested_by' => $this->f->owner->id,
        'status' => BriefStatus::Completed, 'headline' => 'Revenue declined 20% last week.', 'generated_at' => now(),
    ]);
});

test('the meetings pages carry the meeting list, the business date and the latest completed brief', function () {
    makeMeeting($this->f, ['title' => 'Alpha', 'meeting_date' => '2026-09-21']);
    $selected = makeMeeting($this->f, ['title' => 'Beta', 'meeting_date' => '2026-09-28', 'status' => 'ready_for_review', 'transcript' => str_repeat('Transcript text. ', 5)]);
    ($this->brief)(['headline' => 'An older brief.', 'generated_at' => now()->subDay()]);
    $latest = ($this->brief)();
    ($this->brief)(['status' => BriefStatus::Failed, 'headline' => null, 'generated_at' => null]); // never shown

    $props = fn ($p) => $p->has('sidebar', 2)->where('sidebar.0.title', 'Beta')->where('sidebar.0.statusLabel', 'Ready for review')
        ->where('sidebar.1.title', 'Alpha')->has('businessToday')
        ->where('latestBrief.id', $latest->id)->where('latestBrief.headline', 'Revenue declined 20% last week.')->where('latestBrief.url', route('briefs.show', $latest));

    $this->actingAs($this->f->owner)->get('/meetings')->assertOk()->assertInertia($props);
    $this->actingAs($this->f->owner)->get("/meetings/{$selected->id}")->assertOk()->assertInertia(fn ($p) => $props($p)->where('meeting.id', $selected->id));
});

test('the list and the brief strip follow the viewer scope', function () {
    makeMeeting($this->f, ['title' => 'Sales meeting']);
    $support = Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'support', 'name' => 'Support']);
    $supportManager = User::factory()->manager($support)->create();
    makeMeeting($this->f, ['title' => 'Support meeting', 'department_id' => $support->id, 'created_by' => $supportManager->id]);
    ($this->brief)(); // company-wide: an Owner brief, not a Support Manager brief

    $this->actingAs($this->f->owner)->get('/meetings')->assertInertia(fn ($p) => $p->has('sidebar', 2)->where('latestBrief.headline', 'Revenue declined 20% last week.'));
    $this->actingAs($supportManager)->get('/meetings')->assertInertia(fn ($p) => $p->has('sidebar', 1)->where('sidebar.0.title', 'Support meeting')->where('latestBrief', null));
});

test('there is no brief strip when no brief has completed, and staff still get 403', function () {
    makeMeeting($this->f);
    ($this->brief)(['status' => BriefStatus::Queued, 'headline' => null, 'generated_at' => null]);

    $this->actingAs($this->f->owner)->get('/meetings')->assertInertia(fn ($p) => $p->where('latestBrief', null));
    $this->actingAs($this->f->priya)->get('/meetings')->assertForbidden();
});
