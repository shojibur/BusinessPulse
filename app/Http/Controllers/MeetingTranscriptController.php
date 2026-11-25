<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTranscriptRequest;
use App\Http\Requests\UpdateTranscriptRequest;
use App\Models\Meeting;
use App\Services\Meetings\MeetingTranscriptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class MeetingTranscriptController extends Controller
{
    public function __construct(private readonly MeetingTranscriptService $service) {}

    public function store(StoreTranscriptRequest $request, Meeting $meeting): RedirectResponse
    {
        if (! $this->service->storeManual($meeting, $request->user(), $request->string('transcript')->toString())) {
            throw ValidationException::withMessages(['transcript' => 'This meeting is already being processed.']);
        }

        return to_route('meetings.show', $meeting);
    }

    public function update(UpdateTranscriptRequest $request, Meeting $meeting): RedirectResponse
    {
        $result = $this->service->edit($meeting, $request->user(), $request->string('transcript')->toString());

        if ($result === null) {
            throw ValidationException::withMessages(['transcript' => 'The transcript cannot be edited while the meeting is being processed.']);
        }

        return to_route('meetings.show', $meeting);
    }
}
