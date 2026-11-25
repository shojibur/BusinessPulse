<?php

namespace App\Http\Controllers;

use App\Events\Meetings\RecordingUploaded;
use App\Http\Requests\UploadRecordingRequest;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Services\Meetings\RecordingStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class MeetingRecordingController extends Controller
{
    public function store(UploadRecordingRequest $request, Meeting $meeting, RecordingStorage $storage): RedirectResponse
    {
        $mime = $request->detectedMime();

        // Consent, extension, size and content were validated; consent time and user are persisted with the file,
        // before the transcription job can be dispatched.
        if ($mime === null || ! $storage->accept($meeting, $request->file('file'), $request->user(), $mime)) {
            throw ValidationException::withMessages(['file' => 'This meeting is already being processed.']);
        }

        $meeting->refresh();
        RecordingUploaded::dispatch($meeting, (int) $meeting->recording_size, (string) $meeting->recording_mime, $request->user()->id);
        TranscribeMeetingJob::dispatch($meeting->id);

        return to_route('meetings.show', $meeting);
    }
}
