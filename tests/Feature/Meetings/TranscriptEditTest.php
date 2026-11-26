<?php

use App\Enums\MeetingStatus;
use App\Events\Meetings\TranscriptEdited;
use App\Jobs\ExtractMeetingTasksJob;
use App\Models\TaskDraft;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    configureMeetingAi();
    $this->f = meetingRoster();
});

test('a manual transcript is validated, stored and extraction starts without another click', function () {
    Queue::fake();
    $meeting = makeMeeting($this->f);

    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/transcript", ['transcript' => 'short'])->assertSessionHasErrors('transcript');
    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/transcript", ['transcript' => str_repeat('a', 60001)])->assertSessionHasErrors('transcript');
    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/transcript", ['transcript' => '  '.demoTranscript()."\r\n"])->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Transcribed)->and($meeting->transcript_source->value)->toBe('manual')->and($meeting->transcript)->toBe(demoTranscript());
    Queue::assertPushedOn('default', ExtractMeetingTasksJob::class);
    Queue::assertPushed(ExtractMeetingTasksJob::class, 1);

    // A second manual submission is refused: the meeting is already past draft.
    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/transcript", ['transcript' => demoTranscript()])->assertSessionHasErrors('transcript');
});

test('editing keeps the status, records the edit time and does not silently re-extract', function () {
    Event::fake([TranscriptEdited::class]);
    Queue::fake();
    $meeting = makeMeeting($this->f, ['status' => 'ready_for_review', 'transcript' => demoTranscript(), 'transcript_source' => 'audio', 'extracted_at' => now()->subHour()]);
    TaskDraft::create(['meeting_id' => $meeting->id, 'suggested_title' => 'Old draft', 'transcript_excerpt' => 'x', 'extraction_run' => 1]);

    $this->actingAs($this->f->manager)->put("/meetings/{$meeting->id}/transcript", ['transcript' => demoTranscript()])->assertRedirect(); // identical: no-op
    expect($meeting->fresh()->transcript_edited_at)->toBeNull();

    $this->actingAs($this->f->manager)->put("/meetings/{$meeting->id}/transcript", ['transcript' => demoTranscript()."\nAisyah: Added a correction to the notes."])->assertRedirect();
    $meeting->refresh();

    expect($meeting->status)->toBe(MeetingStatus::ReadyForReview)->and($meeting->transcript_edited_at)->not->toBeNull()->and($meeting->transcript_source->value)->toBe('audio')
        ->and(TaskDraft::count())->toBe(1);
    Queue::assertNothingPushed();
    Event::assertDispatchedTimes(TranscriptEdited::class, 1);
    $this->actingAs($this->f->manager)->get("/meetings/{$meeting->id}")->assertInertia(fn ($p) => $p->where('meeting.transcriptChangedSinceExtraction', true));
});

test('the transcript cannot be edited while a job owns the meeting, and staff cannot edit', function () {
    $meeting = makeMeeting($this->f, ['status' => 'extracting', 'transcript' => demoTranscript(), 'transcript_source' => 'manual']);

    $this->actingAs($this->f->manager)->put("/meetings/{$meeting->id}/transcript", ['transcript' => demoTranscript()."\nmore text to edit here"])->assertSessionHasErrors('transcript');
    $this->actingAs($this->f->priya)->put("/meetings/{$meeting->id}/transcript", ['transcript' => demoTranscript()."\nmore"])->assertForbidden();
    expect($meeting->fresh()->transcript)->toBe(demoTranscript());
});

test('re-running extraction after an edit uses the edited transcript', function () {
    $edited = demoTranscript()."\nAisyah: Priya, please send the weekly summary to the owner by tomorrow.";
    $meeting = makeMeeting($this->f, ['status' => 'failed', 'failure_stage' => 'extraction', 'error_message' => 'timeout', 'transcript' => demoTranscript(), 'transcript_source' => 'manual']);
    Http::fake(['*' => Http::response(extractionReply(['decisions' => [], 'task_drafts' => [
        ['title' => 'Send the weekly summary', 'description' => '', 'assignee_name' => 'Priya', 'assignee_id' => null, 'deadline_phrase' => 'tomorrow', 'excerpt' => 'Priya, please send the weekly summary to the owner by tomorrow.'],
    ]]))]);

    $this->actingAs($this->f->manager)->put("/meetings/{$meeting->id}/transcript", ['transcript' => $edited])->assertRedirect();
    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/retry")->assertRedirect();

    Http::assertSent(fn ($r) => str_contains($r['messages'][1]['content'], 'send the weekly summary to the owner'));
    expect(TaskDraft::pluck('suggested_title')->all())->toBe(['Send the weekly summary'])->and(TaskDraft::first()->due_date->format('Y-m-d'))->toBe('2026-09-29');
});
