<?php

use App\Enums\MeetingErrorCode;
use App\Enums\MeetingStatus;
use App\Jobs\ExtractMeetingTasksJob;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\TaskDraft;
use App\Models\User;
use App\Services\AI\MeetingTaskExtractor;
use App\Services\Meetings\MeetingRunGuard;
use App\Services\Meetings\MeetingStageException;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Http::preventStrayRequests();
    configureMeetingAi();
    $this->f = meetingRoster();
});

function runExtraction(Meeting $meeting): void
{
    dispatch_sync(new ExtractMeetingTasksJob($meeting->id));
}

test('the demo transcript produces the four documented drafts and two decisions', function () {
    Http::fake(['*' => Http::response(extractionReply(demoExtraction()))]);
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);
    $meeting->refresh();
    $drafts = TaskDraft::where('meeting_id', $meeting->id)->orderBy('id')->get();

    expect($meeting->status)->toBe(MeetingStatus::ReadyForReview)->and($meeting->decisions)->toHaveCount(2)->and($meeting->extraction_discarded_count)->toBe(0)
        ->and($drafts)->toHaveCount(4)->and($drafts->pluck('extraction_run')->unique()->all())->toBe([1])->and($drafts->pluck('status')->map->value->unique()->all())->toBe(['pending']);

    // 1. Priya, by Friday 2 October 2026.
    expect($drafts[0]->assignee_id)->toBe($this->f->priya->id)->and($drafts[0]->assignee_resolution->value)->toBe('resolved')
        ->and($drafts[0]->due_date->format('Y-m-d'))->toBe('2026-10-02')->and($drafts[0]->deadline_resolution->value)->toBe('resolved')
        ->and($drafts[0]->suggested_due_date_text)->toBe('by Friday');
    // 2. Farid, by next Wednesday 7 October 2026.
    expect($drafts[1]->assignee_id)->toBe($this->f->farid->id)->and($drafts[1]->due_date->format('Y-m-d'))->toBe('2026-10-07');
    // 3. Daniel is ambiguous (Daniel Tan or Daniel Lee) and has no deadline.
    expect($drafts[2]->assignee_resolution->value)->toBe('ambiguous')->and($drafts[2]->assignee_id)->toBeNull()
        ->and($drafts[2]->deadline_resolution->value)->toBe('none')->and($drafts[2]->due_date)->toBeNull();
    // 4. The Saturday stock check has neither assignee nor deadline.
    expect($drafts[3]->assignee_resolution->value)->toBe('none')->and($drafts[3]->deadline_resolution->value)->toBe('none');
    // Every excerpt really occurs in the transcript.
    foreach ($drafts as $draft) {
        expect(demoTranscript())->toContain($draft->transcript_excerpt);
    }
});

test('the prompt injection in the transcript cannot assign any task to the owner', function () {
    $extraction = demoExtraction();
    // A model that obeyed the injected line: every task assigned to the owner, with the owner's real id.
    foreach ($extraction['task_drafts'] as &$draft) {
        $draft['assignee_name'] = 'Lim Wei Han';
        $draft['assignee_id'] = $this->f->owner->id;
    }
    unset($draft);
    Http::fake(['*' => Http::response(extractionReply($extraction))]);
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);

    $drafts = TaskDraft::where('meeting_id', $meeting->id)->get();
    expect($drafts)->toHaveCount(4)->and($drafts->pluck('assignee_id')->filter()->all())->toBe([])
        ->and($drafts->pluck('assignee_resolution')->map->value->unique()->all())->toBe(['unmatched']);
    // The owner is not even offered to the model, and the transcript sits inside the untrusted block.
    Http::assertSent(function (Request $r) {
        $user = $r['messages'][1]['content'];

        return ! str_contains($user, 'weihan@m.test') && ! str_contains($user, '"Lim Wei Han"') && str_contains($user, 'Priya Nair')
            && str_contains($r['messages'][0]['content'], 'untrusted data') && preg_match('/TRANSCRIPT_START_[0-9a-f]{16}/', $user) === 1
            && ! str_contains($user, 'test-key-not-real');
    });
});

test('fabricated ids, invented excerpts and unauthorized people are rejected', function () {
    $outsider = User::factory()->staff(Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'support']))->create(['name' => 'Zainab Other']);
    $extraction = demoExtraction();
    $extraction['task_drafts'][0]['assignee_name'] = 'Zainab Other';       // a real user of ANOTHER department
    $extraction['task_drafts'][0]['assignee_id'] = $outsider->id;
    $extraction['task_drafts'][1]['assignee_id'] = 987654;                  // fabricated id: the name decides
    $extraction['task_drafts'][] = ['title' => 'Fire everyone', 'description' => '', 'assignee_name' => 'Priya', 'assignee_id' => null, 'deadline_phrase' => 'tomorrow', 'excerpt' => 'We agreed to fire the whole sales team tomorrow.'];
    $extraction['decisions'][] = ['summary' => 'Invented decision', 'excerpt' => 'The board approved a twenty percent bonus for everyone.'];
    Http::fake(['*' => Http::response(extractionReply($extraction))]);
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);
    $meeting->refresh();
    $drafts = TaskDraft::where('meeting_id', $meeting->id)->orderBy('id')->get();

    expect($drafts)->toHaveCount(4)->and($drafts[0]->assignee_resolution->value)->toBe('unmatched')->and($drafts[0]->assignee_id)->toBeNull()
        ->and($drafts[1]->assignee_id)->toBe($this->f->farid->id)
        ->and($drafts->pluck('suggested_title')->all())->not->toContain('Fire everyone')
        ->and($meeting->decisions)->toHaveCount(2)->and($meeting->extraction_discarded_count)->toBe(2);
});

test('an invalid extraction gets one corrective request and then fails without drafts or fake results', function () {
    Http::fake(['*' => Http::response(extractionReply(['decisions' => 'nope']))]);
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);
    $meeting->refresh();

    Http::assertSentCount(2);
    expect($meeting->status)->toBe(MeetingStatus::Failed)->and($meeting->failure_stage->value)->toBe('extraction')->and($meeting->error_message)->toBe('invalid_ai_output')
        ->and(TaskDraft::count())->toBe(0)->and($meeting->decisions)->toBeNull();
});

test('a corrective request can repair invalid JSON inside the same attempt', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => 'this is not json']]]])
        ->push(extractionReply(demoExtraction()))]);
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);

    Http::assertSentCount(2);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::ReadyForReview)->and(TaskDraft::count())->toBe(4);
});

test('a provider failure never produces fake drafts and Retry works afterwards', function () {
    Http::fake(['*' => Http::sequence()->push([], 401)->push(extractionReply(demoExtraction()))]);
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);
    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Failed)->and($meeting->error_message)->toBe('client_error')->and(TaskDraft::count())->toBe(0);
    Http::assertSentCount(1); // a 4xx is final: no retry

    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/retry")->assertRedirect();
    expect($meeting->fresh()->status)->toBe(MeetingStatus::ReadyForReview)->and(TaskDraft::count())->toBe(4);
});

test('duplicate dispatch and repeated runs create one set of drafts and one provider call', function () {
    Http::fake(['*' => Http::response(extractionReply(demoExtraction()))]);
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);
    runExtraction($meeting);
    runExtraction($meeting);

    Http::assertSentCount(1);
    expect(TaskDraft::count())->toBe(4)->and($meeting->fresh()->status)->toBe(MeetingStatus::ReadyForReview);
});

test('extraction creates drafts only: no tasks, no notifications, no issue changes', function () {
    Http::fake(['*' => Http::response(extractionReply(demoExtraction()))]);
    $issue = fakeIssue($this->f->sales);
    $before = $issue->only(['status', 'severity', 'resolved_at']);
    $meeting = transcribedMeeting($this->f);
    $meeting->issues()->attach($issue->id);

    runExtraction($meeting);

    expect(TaskDraft::pluck('task_id')->filter()->all())->toBe([])
        ->and(TaskDraft::pluck('reviewed_by')->filter()->all())->toBe([])
        ->and(TaskDraft::pluck('issue_id')->filter()->all())->toBe([])
        ->and(Schema::hasTable('tasks'))->toBeFalse()
        ->and(DatabaseNotification::count())->toBe(0)
        ->and($issue->fresh()->only(['status', 'severity', 'resolved_at']))->toEqual($before);
});

test('relative deadlines use the meeting date, not the date the job runs', function () {
    Http::fake(['*' => Http::response(extractionReply(demoExtraction()))]);
    Carbon::setTestNow('2031-03-15 10:00:00');
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);
    Carbon::setTestNow();

    expect(TaskDraft::where('meeting_id', $meeting->id)->orderBy('id')->first()->due_date->format('Y-m-d'))->toBe('2026-10-02');
});

test('an unusable or empty transcript fails the stage without calling the provider', function () {
    Http::fake();
    $meeting = transcribedMeeting($this->f, 'too short');

    runExtraction($meeting);

    Http::assertNothingSent();
    expect($meeting->fresh()->error_message)->toBe('empty_transcript');
});

test('a transcript that changed during extraction is never overwritten by the stale result', function () {
    $meeting = transcribedMeeting($this->f);
    Http::fake(['*' => function () use ($meeting) {
        // Simulate a newer transcript revision arriving while the provider call is in flight.
        DB::table('meetings')->where('id', $meeting->id)->update(['transcript' => demoTranscript()."\nAisyah: One more point about stock."]);

        return Http::response(extractionReply(demoExtraction()));
    }]);

    runExtraction($meeting);
    $meeting->refresh();

    expect($meeting->status)->toBe(MeetingStatus::Failed)->and($meeting->error_message)->toBe('transcript_changed')->and(TaskDraft::count())->toBe(0);
});

test('excerpts are matched on normalized text and the original text is stored', function () {
    $extraction = demoExtraction();
    $extraction['task_drafts'][0]['excerpt'] = "  priya,   PLEASE call the ten biggest\nlapsed penang accounts by friday. ";
    Http::fake(['*' => Http::response(extractionReply($extraction))]);
    $meeting = transcribedMeeting($this->f);

    runExtraction($meeting);

    expect(TaskDraft::where('meeting_id', $meeting->id)->orderBy('id')->first()->transcript_excerpt)->toBe('Priya, please call the ten biggest lapsed Penang accounts by Friday.');
});

test('the extractor reports discarded items when only some excerpts exist', function () {
    $extraction = ['decisions' => [], 'task_drafts' => [
        ['title' => 'Real', 'description' => '', 'assignee_name' => 'Priya', 'assignee_id' => null, 'deadline_phrase' => null, 'excerpt' => 'Priya, please call the ten biggest lapsed Penang accounts by Friday.'],
        ['title' => 'Invented', 'description' => '', 'assignee_name' => 'Priya', 'assignee_id' => null, 'deadline_phrase' => null, 'excerpt' => 'This sentence never appears anywhere in the transcript.'],
    ]];
    Http::fake(['*' => Http::response(extractionReply($extraction))]);
    $meeting = transcribedMeeting($this->f)->load('department.company');
    $staff = app(MeetingRunGuard::class)->allowedStaff($meeting);

    $result = app(MeetingTaskExtractor::class)->extract($meeting, $staff, collect());

    expect($result->drafts)->toHaveCount(1)->and($result->drafts[0]->title)->toBe('Real')->and($result->discardedCount)->toBe(1);
});

test('an approved duplicate from an earlier run is not recreated', function () {
    Http::fake(['*' => Http::response(extractionReply(demoExtraction()))]);
    $meeting = transcribedMeeting($this->f)->load('department.company');
    $approved = new TaskDraft(['suggested_title' => 'call the ten biggest lapsed penang accounts', 'assignee_id' => $this->f->priya->id]);
    $staff = app(MeetingRunGuard::class)->allowedStaff($meeting);

    $result = app(MeetingTaskExtractor::class)->extract($meeting, $staff, collect([$approved]));

    expect($result->drafts)->toHaveCount(3)->and($result->discardedCount)->toBe(1);
});

test('the stage exception carries a closed code only', function () {
    $e = new MeetingStageException(MeetingErrorCode::InvalidAiOutput);
    expect($e->errorCode->value)->toBe('invalid_ai_output')->and($e->getMessage())->not->toContain('transcript');
});
