<?php

use App\Enums\MeetingStatus;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->f = meetingRoster();
    $this->meeting = makeMeeting($this->f);
});

/** A tiny file whose CONTENT is an MPEG audio frame (finfo detects audio/mpeg). */
function fakeMp3(string $name = 'huddle.mp3', int $extraBytes = 2000): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "\xFF\xFB\x90\x00".str_repeat("\x00", $extraBytes));
}

test('an upload without the consent confirmation is rejected, stores no file and leaves the meeting a draft', function () {
    Queue::fake();

    $this->actingAs($this->f->manager)->post("/meetings/{$this->meeting->id}/recording", ['file' => fakeMp3()])
        ->assertSessionHasErrors('recording_consent');

    expect(Storage::disk('local')->allFiles())->toBe([])->and($this->meeting->fresh()->status)->toBe(MeetingStatus::Draft)
        ->and($this->meeting->fresh()->recording_consent_at)->toBeNull();
    Queue::assertNothingPushed();
});

test('a valid upload records consent, stores a random private file and queues transcription', function () {
    Queue::fake();

    $this->actingAs($this->f->manager)->post("/meetings/{$this->meeting->id}/recording", ['recording_consent' => '1', 'file' => fakeMp3('../../evil name.mp3')])
        ->assertRedirect();
    $meeting = $this->meeting->fresh();

    expect($meeting->status)->toBe(MeetingStatus::Uploaded)
        ->and($meeting->recording_consent_by)->toBe($this->f->manager->id)->and($meeting->recording_consent_at)->not->toBeNull()
        ->and($meeting->recording_path)->toMatch('#^recordings/'.$meeting->id.'/[A-Za-z0-9]{40}\.mp3$#')
        ->and($meeting->recording_original_name)->not->toContain('/')->and($meeting->recording_mime)->toBe('audio/mpeg')
        ->and($meeting->recording_uploaded_at)->not->toBeNull();
    Storage::disk('local')->assertExists($meeting->recording_path);
    Queue::assertPushedOn('ai', TranscribeMeetingJob::class, fn ($job) => $job->meetingId === $meeting->id);
    // Private disk only: nothing under the public disk.
    expect(collect(Storage::disk('public')->allFiles())->filter(fn ($f) => str_contains($f, 'recordings')))->toBeEmpty();
});

test('unsafe files are rejected without storing anything', function (string $case) {
    Queue::fake();
    $file = match ($case) {
        'text renamed mp3' => UploadedFile::fake()->createWithContent('notes.mp3', "just some text, not audio\n"),
        'php renamed mp3' => UploadedFile::fake()->createWithContent('shell.mp3', '<?php echo 1;'),
        'wrong extension' => UploadedFile::fake()->createWithContent('clip.exe', "\xFF\xFB\x90\x00".str_repeat("\0", 100)),
        'zero bytes' => UploadedFile::fake()->createWithContent('empty.mp3', ''),
        'too large' => UploadedFile::fake()->create('big.mp3', 20481, 'audio/mpeg'),
    };

    $this->actingAs($this->f->manager)->post("/meetings/{$this->meeting->id}/recording", ['recording_consent' => '1', 'file' => $file])
        ->assertSessionHasErrors('file');

    expect(Storage::disk('local')->allFiles())->toBe([])->and($this->meeting->fresh()->status)->toBe(MeetingStatus::Draft);
    Queue::assertNothingPushed();
})->with(['text renamed mp3', 'php renamed mp3', 'wrong extension', 'zero bytes', 'too large']);

test('a second upload and an upload after a transcript are rejected', function () {
    Queue::fake();
    $this->actingAs($this->f->manager)->post("/meetings/{$this->meeting->id}/recording", ['recording_consent' => '1', 'file' => fakeMp3()])->assertRedirect();
    $this->actingAs($this->f->manager)->post("/meetings/{$this->meeting->id}/recording", ['recording_consent' => '1', 'file' => fakeMp3('second.mp3')])->assertSessionHasErrors('file');
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);

    $withTranscript = makeMeeting($this->f, ['transcript' => demoTranscript(), 'status' => 'transcribed', 'transcript_source' => 'manual']);
    $this->actingAs($this->f->manager)->post("/meetings/{$withTranscript->id}/recording", ['recording_consent' => '1', 'file' => fakeMp3()])->assertSessionHasErrors('file');
});

test('upload is limited to 10 per minute', function () {
    Queue::fake();
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($this->f->manager)->post("/meetings/{$this->meeting->id}/recording", ['recording_consent' => '1']);
    }

    $this->actingAs($this->f->manager)->post("/meetings/{$this->meeting->id}/recording", ['recording_consent' => '1'])->assertStatus(429)->assertHeader('Retry-After');
});

test('staff and managers of other departments cannot upload', function () {
    Queue::fake();
    $other = Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'support']);
    $otherManager = User::factory()->manager($other)->create();

    $this->actingAs($this->f->priya)->post("/meetings/{$this->meeting->id}/recording", ['recording_consent' => '1', 'file' => fakeMp3()])->assertForbidden();
    $this->actingAs($otherManager)->post("/meetings/{$this->meeting->id}/recording", ['recording_consent' => '1', 'file' => fakeMp3()])->assertNotFound();
    expect(Storage::disk('local')->allFiles())->toBe([])->and(Meeting::find($this->meeting->id)->status)->toBe(MeetingStatus::Draft);
});
