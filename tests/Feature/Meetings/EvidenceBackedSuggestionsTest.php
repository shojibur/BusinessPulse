<?php

use App\Jobs\ExtractMeetingTasksJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Department;
use App\Models\TaskDraft;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    configureMeetingAi();
    $this->f = meetingRoster();
});

function runWith(array $task, stdClass $f): TaskDraft
{
    $extraction = ['decisions' => [], 'task_drafts' => [$task + ['description' => '', 'assignee_id' => null]]];
    Http::fake(['*' => Http::response(extractionReply($extraction))]);
    $meeting = transcribedMeeting($f);
    dispatch_sync(new ExtractMeetingTasksJob($meeting->id, $f->manager->id));

    return TaskDraft::firstOrFail();
}

test('a name the quote does not contain is never resolved, even when it matches a staff member', function () {
    // The quote is about Priya; the model claims Farid. "Farid" exists, but the evidence does not support it.
    $draft = runWith(['title' => 'Call the accounts', 'assignee_name' => 'Farid', 'deadline_phrase' => null, 'excerpt' => 'Priya, please call the ten biggest lapsed Penang accounts by Friday.'], $this->f);

    expect($draft->assignee_resolution->value)->toBe('unmatched')->and($draft->assignee_id)->toBeNull()->and($draft->suggested_assignee_name)->toBe('Farid');
});

test('a deadline phrase the quote does not contain is never resolved, even when it is a recognizable date', function () {
    $draft = runWith(['title' => 'Call the accounts', 'assignee_name' => 'Priya', 'deadline_phrase' => 'tomorrow', 'excerpt' => 'Priya, please call the ten biggest lapsed Penang accounts by Friday.'], $this->f);

    expect($draft->assignee_id)->toBe($this->f->priya->id)->and($draft->deadline_resolution->value)->toBe('ambiguous')->and($draft->due_date)->toBeNull()
        ->and($draft->suggested_due_date_text)->toBe('tomorrow');
});

test('a user who lost access cannot cause provider processing: transcription and extraction both stop before any call', function () {
    Storage::disk('local')->put('recordings/1/a.mp3', "\xFF\xFB\x90\x00".str_repeat("\x00", 100));
    Http::fake();

    foreach (['demoted to staff' => fn (User $u) => $u->update(['role' => 'staff']), 'deactivated' => fn (User $u) => $u->forceFill(['is_active' => false])->save(),
        'moved department' => fn (User $u) => $u->update(['department_id' => Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'ops'.rand(1, 99999)])->id])] as $case => $change) {
        $manager = User::factory()->manager($this->f->sales)->create();
        $meeting = makeMeeting($this->f, [
            'status' => 'uploaded', 'created_by' => $manager->id, 'recording_path' => 'recordings/1/a.mp3', 'recording_mime' => 'audio/mpeg', 'recording_size' => 104,
            'recording_uploaded_at' => now(), 'recording_consent_at' => now(), 'recording_consent_by' => $manager->id,
        ]);
        $change($manager);

        dispatch_sync(new TranscribeMeetingJob($meeting->id, $manager->id));

        expect($meeting->fresh()->status->value)->toBe('failed', $case)->and($meeting->fresh()->error_message)->toBe('actor_unauthorized', $case);
    }
    Http::assertNothingSent();
});

test('an extraction requested by a user who lost access fails without calling the provider', function () {
    Http::fake();
    $manager = User::factory()->manager($this->f->sales)->create();
    $meeting = transcribedMeeting($this->f);
    $manager->forceFill(['is_active' => false])->save();

    dispatch_sync(new ExtractMeetingTasksJob($meeting->id, $manager->id));

    Http::assertNothingSent();
    expect($meeting->fresh()->error_message)->toBe('actor_unauthorized')->and(TaskDraft::count())->toBe(0);
});

test('the consent giver must still be authorized when an audio transcript is extracted later', function () {
    Http::fake();
    $meeting = transcribedMeeting($this->f);
    $meeting->update(['transcript_source' => 'audio', 'recording_consent_at' => now(), 'recording_consent_by' => $this->f->priya->id]); // consent by a Staff user: not authorized

    dispatch_sync(new ExtractMeetingTasksJob($meeting->id, $this->f->manager->id));

    Http::assertNothingSent();
    expect($meeting->fresh()->error_message)->toBe('actor_unauthorized');
});
