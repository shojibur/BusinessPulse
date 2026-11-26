<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\RuleKey;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskDraft;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->f = meetingRoster();
    $this->x = reviewFixture($this->f);
});

function approve(TaskDraft $draft, array $body = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->f->owner)->post("/task-drafts/{$draft->id}/approve", $body);
}

test('drafts are not tasks: extraction output leaves the tasks table empty and approval is human', function () {
    expect(Task::count())->toBe(0)->and(TaskDraft::count())->toBe(4);

    approve($this->x->drafts[0])->assertRedirect();

    $task = Task::firstOrFail();
    expect(Task::count())->toBe(1)->and($task->status)->toBe(TaskStatus::Pending)->and($task->assignee_id)->toBe($this->f->priya->id)
        ->and($task->due_date->format('Y-m-d'))->toBe('2026-10-02')->and($task->assigned_by)->toBe($this->f->owner->id)
        ->and($task->department_id)->toBe($this->f->sales->id)->and($task->meeting_id)->toBe($this->x->meeting->id)->and($task->task_draft_id)->toBe($this->x->drafts[0]->id);
    $draft = $this->x->drafts[0]->fresh();
    expect($draft->status->value)->toBe('approved')->and($draft->task_id)->toBe($task->id)->and($draft->reviewed_by)->toBe($this->f->owner->id)->and($draft->reviewed_at)->not->toBeNull();
});

test('owner and the manager of the meeting department can approve, staff cannot', function () {
    approve($this->x->drafts[0], [], $this->f->manager)->assertRedirect();
    approve($this->x->drafts[1], [], $this->f->priya)->assertForbidden();
    expect(Task::count())->toBe(1)->and($this->x->drafts[1]->fresh()->status->value)->toBe('pending');
});

test('approval is refused with 422 for an invalid assignee or deadline and creates nothing', function (string $case, string $field) {
    $f = $this->f;
    $draft = $this->x->drafts[0];
    $inactive = User::factory()->staff($f->sales)->inactive()->create(['name' => 'Ivan Gone']);
    $otherDepartment = User::factory()->staff(Department::factory()->create(['company_id' => $f->company->id, 'slug' => 'support']))->create();

    $body = match ($case) {
        'inactive assignee' => ['assignee_id' => $inactive->id],
        'other department assignee' => ['assignee_id' => $otherDepartment->id],
        'owner as assignee' => ['assignee_id' => $f->owner->id],
        'unknown assignee' => ['assignee_id' => 987654],
        'cleared assignee' => ['assignee_id' => null],
        'cleared deadline' => ['due_date' => null],
        'before meeting date' => ['due_date' => '2026-09-27'],
        'malformed date' => ['due_date' => '2026-02-30'],
        'issue outside the meeting' => ['issue_id' => fakeIssue($f->sales, IssueStatus::Open, IssueSeverity::Low, RuleKey::SalesTargetShortfall)->id],
    };

    approve($draft, $body)->assertSessionHasErrors($field);

    expect(Task::count())->toBe(0)->and($draft->fresh()->status->value)->toBe('pending');
})->with([
    ['inactive assignee', 'assignee_id'], ['other department assignee', 'assignee_id'], ['owner as assignee', 'assignee_id'], ['unknown assignee', 'assignee_id'],
    ['cleared assignee', 'assignee_id'], ['cleared deadline', 'due_date'], ['before meeting date', 'due_date'], ['malformed date', 'due_date'],
    ['issue outside the meeting', 'issue_id'],
]);

test('an ambiguous or missing suggestion cannot be approved without an explicit human choice', function () {
    [, , $daniel, $stock] = $this->x->drafts;

    // Ambiguous Daniel: no assignee, no deadline.
    approve($daniel)->assertSessionHasErrors(['assignee_id', 'due_date']);
    approve($daniel, ['due_date' => '2026-10-03'])->assertSessionHasErrors('assignee_id');
    approve($daniel, ['assignee_id' => $this->f->danielTan->id])->assertSessionHasErrors('due_date');
    expect(Task::count())->toBe(0);

    approve($daniel, ['assignee_id' => $this->f->danielTan->id, 'due_date' => '2026-10-03'])->assertRedirect();
    $draft = $daniel->fresh();
    expect($draft->assignee_resolution->value)->toBe('resolved')->and($draft->deadline_resolution->value)->toBe('resolved')
        ->and($draft->suggested_assignee_name)->toBe('Daniel')->and($draft->suggested_due_date_text)->toBeNull();

    // The Saturday stock check has neither.
    approve($stock)->assertSessionHasErrors(['assignee_id', 'due_date']);
    expect(Task::count())->toBe(1);
});

test('the request body cannot set workflow fields', function () {
    approve($this->x->drafts[0], ['status' => 'completed', 'assigned_by' => $this->f->priya->id, 'task_draft_id' => $this->x->drafts[1]->id, 'department_id' => 999, 'task_id' => 1, 'reviewed_by' => $this->f->priya->id, 'meeting_id' => 999])->assertRedirect();

    $task = Task::firstOrFail();
    expect($task->status)->toBe(TaskStatus::Pending)->and($task->assigned_by)->toBe($this->f->owner->id)->and($task->task_draft_id)->toBe($this->x->drafts[0]->id)
        ->and($task->department_id)->toBe($this->f->sales->id)->and($task->completed_at)->toBeNull()->and($this->x->drafts[0]->fresh()->reviewed_by)->toBe($this->f->owner->id);
});

test('any transcript revision after extraction blocks approval, even when the quote still appears', function () {
    $meeting = $this->x->meeting;
    // Only the context around Priya's quote changes: the quote itself is untouched, but the revision is not the extracted one.
    $meeting->update(['transcript' => str_replace('Last week our revenue', 'Two weeks ago our revenue', demoTranscript()), 'transcript_edited_at' => now()->addMinute()]);
    expect(str_contains($meeting->fresh()->transcript, 'Priya, please call the ten biggest lapsed Penang accounts by Friday.'))->toBeTrue();

    approve($this->x->drafts[0])->assertSessionHasErrors('draft');
    approve($this->x->drafts[1])->assertSessionHasErrors('draft');
    expect(Task::count())->toBe(0);

    $this->actingAs($this->f->owner)->get("/meetings/{$meeting->id}")->assertInertia(fn ($p) => $p
        ->where('draftReview.pending.0.stale', true)->where('draftReview.transcriptChangedSinceExtraction', true));
});

test('a changed quote and an active re-extraction both block approval', function () {
    $meeting = $this->x->meeting;
    $meeting->update(['transcript' => str_replace('by Friday', 'by Monday', demoTranscript())]);
    approve($this->x->drafts[0])->assertSessionHasErrors('draft');

    $meeting->update(['transcript' => demoTranscript(), 'status' => 'extracting']); // same revision, but a re-extraction is running
    approve($this->x->drafts[1])->assertSessionHasErrors('draft');
    expect(Task::count())->toBe(0);

    $meeting->update(['status' => 'ready_for_review']);
    approve($this->x->drafts[1])->assertRedirect();
    expect(Task::count())->toBe(1);
});

test('re-extraction supersedes pending drafts and preserves approved history', function () {
    approve($this->x->drafts[0])->assertRedirect();
    $task = Task::firstOrFail();
    config(['ai.base_url' => 'https://ai.example.test/v1', 'ai.api_key' => 'k', 'ai.text_model' => 'm']);
    Http::fake(['*' => Http::response(extractionReply(demoExtraction()))]);

    $this->actingAs($this->f->owner)->post("/meetings/{$this->x->meeting->id}/re-extract")->assertRedirect();

    $statuses = TaskDraft::orderBy('id')->get()->map(fn ($d) => $d->status->value."#{$d->extraction_run}")->all();
    expect($this->x->drafts[0]->fresh()->status->value)->toBe('approved')->and($this->x->drafts[0]->fresh()->task_id)->toBe($task->id)
        ->and(TaskDraft::where('extraction_run', 1)->where('status', 'superseded')->count())->toBe(3)
        // The duplicate of the approved Priya task is discarded; three new pending drafts belong to run 2.
        ->and(TaskDraft::where('extraction_run', 2)->where('status', 'pending')->count())->toBe(3)
        ->and(Task::count())->toBe(1)->and($statuses)->toContain('approved#1');
});
