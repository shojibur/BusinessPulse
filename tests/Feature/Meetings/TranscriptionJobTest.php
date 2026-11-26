<?php

use App\Enums\MeetingStatus;
use App\Events\Meetings\ExtractionCompleted;
use App\Events\Meetings\MeetingProcessingFailed;
use App\Events\Meetings\TranscriptReady;
use App\Jobs\ExtractMeetingTasksJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Models\TaskDraft;
use App\Services\AI\Exceptions\RecoverableProviderException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    configureMeetingAi();
    $this->f = meetingRoster();
    Storage::disk('local')->put('recordings/1/a.mp3', "\xFF\xFB\x90\x00".str_repeat("\x00", 300));
});

function uploadedMeeting(array $attributes = []): Meeting
{
    return makeMeeting(test()->f, $attributes + [
        'status' => 'uploaded', 'recording_path' => 'recordings/1/a.mp3', 'recording_original_name' => 'real name.mp3', 'recording_mime' => 'audio/mpeg',
        'recording_size' => 304, 'recording_uploaded_at' => now(), 'recording_consent_at' => now(), 'recording_consent_by' => test()->f->manager->id,
    ]);
}

/** Runs the job as if it were queue attempt $attempt. */
function runTranscription(Meeting $meeting, int $attempt = 1): void
{
    $job = new class($meeting->id, $attempt) extends TranscribeMeetingJob
    {
        public function __construct(int $meetingId, private int $attempt)
        {
            parent::__construct($meetingId);
        }

        public function attempts(): int
        {
            return $this->attempt;
        }
    };
    app()->call([$job, 'handle']);
}

test('a recording is transcribed through the provider and extraction follows automatically', function () {
    Event::fake([TranscriptReady::class, ExtractionCompleted::class]);
    Http::fake([
        '*/audio/transcriptions' => Http::response(['text' => demoTranscript()]),
        '*/chat/completions' => Http::response(extractionReply(demoExtraction())),
    ]);
    $meeting = uploadedMeeting();

    runTranscription($meeting);
    $meeting->refresh();

    expect($meeting->status)->toBe(MeetingStatus::ReadyForReview)->and($meeting->transcript)->toBe(demoTranscript())->and($meeting->transcript_source->value)->toBe('audio')
        ->and(TaskDraft::count())->toBe(4);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/audio/transcriptions')
        && $r->hasHeader('Authorization', 'Bearer test-key-not-real')
        && collect($r->data())->contains(fn ($part) => ($part['name'] ?? null) === 'model' && $part['contents'] === 'transcribe-model-x')
        && collect($r->data())->contains(fn ($part) => ($part['name'] ?? null) === 'file' && $part['filename'] === 'recording.mp3')
        && ! str_contains(json_encode($r->data()), 'real name'));
    Event::assertDispatchedTimes(TranscriptReady::class, 1);
    Event::assertDispatchedTimes(ExtractionCompleted::class, 1);
});

test('a missing consent or a deleted recording fails the stage and sends nothing to the provider', function (array $attributes, string $code) {
    Http::fake();
    $meeting = uploadedMeeting($attributes);

    runTranscription($meeting);
    $meeting->refresh();

    Http::assertNothingSent();
    expect($meeting->status)->toBe(MeetingStatus::Failed)->and($meeting->failure_stage->value)->toBe('transcription')->and($meeting->error_message)->toBe($code)->and($meeting->transcript)->toBeNull();
})->with([
    'consent missing' => [['recording_consent_at' => null, 'recording_consent_by' => null], 'consent_missing'],
    'recording deleted' => [['recording_path' => null, 'recording_deleted_at' => now()], 'recording_deleted'],
    'file missing' => [['recording_path' => 'recordings/1/gone.mp3'], 'recording_missing'],
]);

test('an unknown meeting id exits without effect', function () {
    Http::fake();
    app()->call([new TranscribeMeetingJob(999999), 'handle']);
    Http::assertNothingSent();
});

test('a missing provider configuration fails without a call and manual transcript remains possible', function () {
    config(['ai.transcription_model' => null]);
    Http::fake(['*/chat/completions' => Http::response(extractionReply(demoExtraction()))]);
    $meeting = uploadedMeeting();

    runTranscription($meeting);
    Http::assertNothingSent(); // no transcription call was made
    expect($meeting->fresh()->error_message)->toBe('provider_not_configured');

    // Documented manual fallback: no fake transcript was created, and pasting one moves the meeting on.
    expect($meeting->fresh()->transcript)->toBeNull();
    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/transcript", ['transcript' => demoTranscript()])->assertRedirect();
    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::ReadyForReview)->and($meeting->transcript_source->value)->toBe('manual')->and(TaskDraft::count())->toBe(4);
});

test('recoverable provider failures are retried by the queue and fail only on the final attempt', function (mixed $fake, string $code) {
    Event::fake([MeetingProcessingFailed::class]);
    Http::fake(['*' => $fake === 'timeout' ? fn () => throw new ConnectionException('timed out') : Http::response([], $fake)]);
    $meeting = uploadedMeeting();

    expect(fn () => runTranscription($meeting, 1))->toThrow(RecoverableProviderException::class);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Transcribing);

    expect(fn () => runTranscription($meeting, 2))->toThrow(RecoverableProviderException::class);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Transcribing);

    runTranscription($meeting, 3);
    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Failed)->and($meeting->failure_stage->value)->toBe('transcription')->and($meeting->error_message)->toBe($code)->and($meeting->transcript)->toBeNull();
    Event::assertDispatchedTimes(MeetingProcessingFailed::class, 1);
})->with([['timeout', 'timeout'], [429, 'rate_limited'], [503, 'server_error']]);

test('a 4xx or an unparseable answer fails at once without retry and never stores provider text', function (mixed $response, string $code) {
    Http::fake(['*' => $response]);
    $meeting = uploadedMeeting();

    runTranscription($meeting); // attempt 1: a non-recoverable error must not be rethrown
    $meeting->refresh();

    Http::assertSentCount(1);
    expect($meeting->status)->toBe(MeetingStatus::Failed)->and($meeting->error_message)->toBe($code)->and($meeting->transcript)->toBeNull();
})->with([
    '401' => [fn () => Http::response(['error' => ['message' => 'bad key sk-secret']], 401), 'client_error'],
    'garbage' => [fn () => Http::response('not json at all', 200), 'invalid_response'],
    'no text field' => [fn () => Http::response(['nope' => 1]), 'invalid_response'],
]);

test('an empty provider transcript fails instead of inventing text', function () {
    Http::fake(['*' => Http::response(['text' => '   '])]);
    $meeting = uploadedMeeting();

    runTranscription($meeting);

    expect($meeting->fresh()->error_message)->toBe('empty_transcript')->and($meeting->fresh()->transcript)->toBeNull();
});

test('duplicate transcription dispatches make one provider call', function () {
    Http::fake([
        '*/audio/transcriptions' => Http::response(['text' => demoTranscript()]),
        '*/chat/completions' => Http::response(extractionReply(demoExtraction())),
    ]);
    $meeting = uploadedMeeting();

    runTranscription($meeting);
    runTranscription($meeting);

    expect(Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/audio/transcriptions')))->toHaveCount(1);
});

test('retry after a failed transcription re-checks the recording and consent', function () {
    Http::fake(['*' => Http::sequence()->push([], 401)->push(['text' => demoTranscript()])->push(extractionReply(demoExtraction()))]);
    $meeting = uploadedMeeting();
    runTranscription($meeting);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Failed);

    // Consent withdrawn before the retry: the retry is refused and nothing is sent.
    $meeting->update(['recording_consent_at' => null, 'recording_consent_by' => null]);
    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/retry")->assertSessionHasErrors('retry');
    Http::assertSentCount(1);

    $meeting->update(['recording_consent_at' => now(), 'recording_consent_by' => $this->f->manager->id]);
    $this->actingAs($this->f->manager)->post("/meetings/{$meeting->id}/retry")->assertRedirect();
    expect($meeting->fresh()->status)->toBe(MeetingStatus::ReadyForReview);
});

test('the job settings follow the retry layering', function () {
    $transcribe = new TranscribeMeetingJob(1);
    $extract = new ExtractMeetingTasksJob(1);

    expect($transcribe->queue)->toBe('default')->and($transcribe->tries)->toBe(3)->and($transcribe->timeout)->toBe(150)->and($transcribe->backoff())->toBe([10, 30, 60])
        ->and($extract->queue)->toBe('default')->and($extract->tries)->toBe(3)->and($extract->timeout)->toBe(90)->and($extract->backoff())->toBe([10, 30, 60])
        ->and($transcribe)->toBeInstanceOf(ShouldBeUnique::class)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(150)
        ->and(config('ai.timeouts.transcription'))->toBeLessThan($transcribe->timeout);
});
