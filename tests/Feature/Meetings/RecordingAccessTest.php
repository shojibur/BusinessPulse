<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->f = meetingRoster();
    Storage::disk('local')->put('recordings/1/secret.mp3', "\xFF\xFB\x90\x00".str_repeat("\x00", 500));
    $this->meeting = makeMeeting($this->f, [
        'status' => 'transcribed', 'recording_path' => 'recordings/1/secret.mp3', 'recording_original_name' => 'Huddle (Mon).mp3', 'recording_mime' => 'audio/mpeg',
        'recording_size' => 504, 'recording_uploaded_at' => now(), 'recording_consent_at' => now(), 'recording_consent_by' => $this->f->manager->id,
    ]);
    $this->urls = ["/meetings/{$this->meeting->id}/recording/stream", "/meetings/{$this->meeting->id}/recording/download"];
});

test('the owner and the own-department manager can stream and download', function () {
    foreach ($this->urls as $url) {
        $this->actingAs($this->f->owner)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->f->manager)->get($url)->assertOk();
    }
    $this->actingAs($this->f->manager)->get($this->urls[0])->assertHeader('Content-Type', 'audio/mpeg');
    expect($this->actingAs($this->f->manager)->get($this->urls[1])->headers->get('Content-Disposition'))->toContain('attachment')->toContain('Huddle%20%28Mon%29.mp3');
});

test('staff are forbidden, other-department managers and other companies get 404, guests go to login', function () {
    $otherDepartment = Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'support']);
    $otherManager = User::factory()->manager($otherDepartment)->create();
    $otherOwner = User::factory()->owner()->create(['company_id' => Company::factory()->create()->id]);

    foreach ($this->urls as $url) {
        $this->actingAs($this->f->priya)->get($url)->assertForbidden();
        $this->actingAs($otherManager)->get($url)->assertNotFound();
        $this->actingAs($otherOwner)->get($url)->assertNotFound();
        auth()->logout();
        $this->get($url)->assertRedirect('/login');
    }
});

test('a deleted or missing recording is 404 and the stored path is never exposed in props', function () {
    $this->actingAs($this->f->manager)->get("/meetings/{$this->meeting->id}")->assertOk()->assertInertia(fn ($page) => $page
        ->where('meeting.recording.present', true)->where('meeting.recording.streamUrl', url("/meetings/{$this->meeting->id}/recording/stream"))
        ->missing('meeting.recording.path'));
    expect(json_encode($this->actingAs($this->f->manager)->get("/meetings/{$this->meeting->id}")->viewData('page')['props']))->not->toContain('secret.mp3');

    Storage::disk('local')->delete('recordings/1/secret.mp3');
    $this->actingAs($this->f->manager)->get($this->urls[0])->assertNotFound();
});
