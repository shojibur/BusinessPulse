<?php

use App\Enums\MeetingStatus;
use App\Jobs\ExtractMeetingTasksJob;
use App\Models\TaskDraft;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    configureMeetingAi();
    $this->f = meetingRoster();
    $this->x = reviewFixture($this->f);
    $this->url = "/meetings/{$this->x->meeting->id}/re-extract";
});

test('re-extract on a ready meeting sets extracting and dispatches the extraction job', function () {
    Bus::fake([ExtractMeetingTasksJob::class]);

    $this->actingAs($this->f->manager)->post($this->url)->assertRedirect("/meetings/{$this->x->meeting->id}");

    expect($this->x->meeting->fresh()->status)->toBe(MeetingStatus::Extracting);
    Bus::assertDispatched(ExtractMeetingTasksJob::class, fn ($job) => $job->meetingId === $this->x->meeting->id);
});

test('re-extract is a validation error for any other status and dispatches nothing', function () {
    Bus::fake([ExtractMeetingTasksJob::class]);

    foreach (['draft', 'uploaded', 'transcribing', 'transcribed', 'extracting', 'failed'] as $status) {
        $this->x->meeting->update(['status' => $status, 'failure_stage' => $status === 'failed' ? 'extraction' : null]);
        $this->actingAs($this->f->manager)->post($this->url)->assertSessionHasErrors('retry');
        expect($this->x->meeting->fresh()->status->value)->toBe($status);
    }

    Bus::assertNothingDispatched();
});

test('a failed re-extraction leaves every draft and decision unchanged and deletes nothing', function () {
    $before = TaskDraft::orderBy('id')->get()->map->only(['id', 'status', 'suggested_title', 'extraction_run', 'task_id'])->all();
    $decisions = $this->x->meeting->fresh()->decisions;
    Http::fake(['*' => Http::response([], 401)]);

    $this->actingAs($this->f->manager)->post($this->url)->assertRedirect();

    $meeting = $this->x->meeting->fresh();
    expect($meeting->status)->toBe(MeetingStatus::Failed)->and($meeting->failure_stage->value)->toBe('extraction')
        ->and(TaskDraft::orderBy('id')->get()->map->only(['id', 'status', 'suggested_title', 'extraction_run', 'task_id'])->all())->toEqual($before)
        ->and($meeting->decisions)->toEqual($decisions);
});
