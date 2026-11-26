<?php

use App\Enums\MeetingStatus;
use App\Events\Intelligence\EvaluationRunCompleted;
use App\Events\Meetings\TranscriptReady;
use App\Jobs\ExtractMeetingTasksJob;
use App\Jobs\VerifyOutcomesJob;
use App\Listeners\Activity\RecordActivity;
use App\Listeners\Outcomes\DispatchOutcomeVerification;
use App\Models\ActivityLog;
use App\Services\Demo\DemoResetFlag;
use App\Services\Outcomes\OutcomeVerifier;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    configureMeetingAi();
    $this->f = meetingRoster();
    app(DemoResetFlag::class)->set();
});

afterEach(fn () => app(DemoResetFlag::class)->clear());

test('background jobs exit without effect while a demo reset is running (RF-01)', function () {
    $meeting = transcribedMeeting($this->f);
    $this->mock(OutcomeVerifier::class)->shouldNotReceive('verifyDepartment');

    dispatch_sync(new ExtractMeetingTasksJob($meeting->id));
    dispatch_sync(new VerifyOutcomesJob($this->f->sales->id));

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Transcribed);
    Http::assertNothingSent();
});

test('listeners do nothing while a demo reset is running (RF-01)', function () {
    $meeting = transcribedMeeting($this->f);
    Bus::fake([VerifyOutcomesJob::class]);

    (new RecordActivity)->handle(new TranscriptReady($meeting, $this->f->owner->id));
    (new DispatchOutcomeVerification)->handle(new EvaluationRunCompleted($this->f->sales->id, null, null, []));

    expect(ActivityLog::count())->toBe(0);
    Bus::assertNothingDispatched();

    // Control: the same listener records once the flag is cleared.
    app(DemoResetFlag::class)->clear();
    (new RecordActivity)->handle(new TranscriptReady($meeting, $this->f->owner->id));
    expect(ActivityLog::count())->toBeGreaterThan(0);
});

test('the same job runs normally once the flag is cleared', function () {
    app(DemoResetFlag::class)->clear();
    $meeting = transcribedMeeting($this->f);
    Http::fake(['*' => Http::response(extractionReply(demoExtraction()))]);

    dispatch_sync(new ExtractMeetingTasksJob($meeting->id));

    expect($meeting->fresh()->status)->toBe(MeetingStatus::ReadyForReview);
});
