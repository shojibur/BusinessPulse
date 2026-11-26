<?php

use App\Events\Meetings\RecordingDeleted;
use App\Models\Company;
use App\Models\User;
use App\Services\Meetings\RecordingDeleter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Event::fake([RecordingDeleted::class]);
    $this->f = meetingRoster();
    $this->recording = function (array $attributes = [], string $file = 'a.mp3') {
        $meeting = makeMeeting($this->f, $attributes + ['status' => 'ready_for_review', 'transcript' => str_repeat('Transcript text. ', 5)]);
        $path = "recordings/{$meeting->id}/{$file}";
        Storage::disk('local')->put($path, 'audio');
        $meeting->update(['recording_path' => $path, 'recording_uploaded_at' => $attributes['recording_uploaded_at'] ?? now()]);

        return $meeting->refresh();
    };
});

test('the purge deletes only recordings older than the retention period and keeps transcript, decisions and drafts', function () {
    $this->f->company->update(['recording_retention_days' => 30]);
    $old = ($this->recording)(['recording_uploaded_at' => now()->subDays(31), 'decisions' => [['text' => 'Keep']]]);
    $recent = ($this->recording)(['recording_uploaded_at' => now()->subDays(29)]);

    $this->artisan('recordings:purge')->expectsOutput('Purged: 1. Failed: 0.')->assertExitCode(0);

    $old->refresh();
    expect($old->recording_path)->toBeNull()->and($old->recording_deleted_at)->not->toBeNull()
        ->and($old->transcript)->not->toBeNull()->and($old->decisions)->toEqual([['text' => 'Keep']])
        ->and(Storage::disk('local')->exists("recordings/{$old->id}/a.mp3"))->toBeFalse()
        ->and($recent->refresh()->recording_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($recent->recording_path))->toBeTrue();
    Event::assertDispatched(RecordingDeleted::class, fn ($e) => $e->meetingId === $old->id && $e->reason === 'retention' && $e->actorUserId === null);
});

test('the purge uses each company retention period and skips transcribing meetings', function () {
    $this->f->company->update(['recording_retention_days' => 10]);
    $busy = ($this->recording)(['status' => 'transcribing', 'transcript' => null, 'recording_uploaded_at' => now()->subDays(60)]);
    $due = ($this->recording)(['recording_uploaded_at' => now()->subDays(11)]);

    $this->artisan('recordings:purge')->expectsOutput('Purged: 1. Failed: 0.');

    expect($busy->refresh()->recording_path)->not->toBeNull()->and($due->refresh()->recording_path)->toBeNull();
});

test('a filesystem error leaves the meeting unchanged and is counted as failed', function () {
    $meeting = ($this->recording)(['recording_uploaded_at' => now()->subDays(40)]);
    $path = $meeting->recording_path;
    // The file cannot be removed: a disk whose delete() reports success but leaves the file in place.
    Storage::shouldReceive('disk')->with('local')->andReturn($disk = Mockery::mock());
    $disk->shouldReceive('exists')->with($path)->andReturn(true);
    $disk->shouldReceive('delete')->with($path)->andReturn(false);

    expect(app(RecordingDeleter::class)->delete($meeting, 'retention'))->toBe('failed');
    expect($meeting->refresh()->recording_path)->toBe($path)->and($meeting->recording_deleted_at)->toBeNull();
    Event::assertNotDispatched(RecordingDeleted::class);
});

test('the purge is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'recordings:purge'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 2 * * *')->and($event->withoutOverlapping)->toBeTrue();
});

test('the owner can delete a recording early, the transcript is kept and the event carries the reason', function () {
    $meeting = ($this->recording)();

    $this->actingAs($this->f->owner)->delete("/meetings/{$meeting->id}/recording")->assertRedirect("/meetings/{$meeting->id}");

    expect($meeting->refresh()->recording_path)->toBeNull()->and($meeting->recording_deleted_at)->not->toBeNull()->and($meeting->transcript)->not->toBeNull();
    Event::assertDispatched(RecordingDeleted::class, fn ($e) => $e->reason === 'owner_request' && $e->actorUserId === $this->f->owner->id);

    $this->actingAs($this->f->owner)->get("/meetings/{$meeting->id}")->assertOk()->assertInertia(fn ($p) => $p
        ->where('meeting.recording.deletedAt', fn ($v) => $v !== null)
        ->where('meeting.recording.downloadUrl', null)
        ->where('meeting.can.uploadRecording', false));
    $this->actingAs($this->f->owner)->get("/meetings/{$meeting->id}/recording/download")->assertNotFound();
});

test('managers and staff cannot delete, other companies get 404, guests go to login', function () {
    $meeting = ($this->recording)();
    $otherOwner = User::factory()->owner()->create(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($this->f->manager)->delete("/meetings/{$meeting->id}/recording")->assertForbidden();
    $this->actingAs($this->f->priya)->delete("/meetings/{$meeting->id}/recording")->assertForbidden();
    $this->actingAs($otherOwner)->delete("/meetings/{$meeting->id}/recording")->assertNotFound();
    auth()->logout();
    $this->delete("/meetings/{$meeting->id}/recording")->assertRedirect('/login');

    expect($meeting->refresh()->recording_path)->not->toBeNull();
});
