<?php

namespace App\Http\Controllers;

use App\Enums\AudioStatus;
use App\Enums\BriefStatus;
use App\Http\Requests\GenerateBriefAudioRequest;
use App\Jobs\GenerateBriefAudioJob;
use App\Models\Brief;
use App\Services\AI\OpenAiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class BriefAudioController extends Controller
{
    public function store(GenerateBriefAudioRequest $request, Brief $brief): RedirectResponse
    {
        if (! OpenAiClient::speechConfigured()) {
            throw ValidationException::withMessages(['audio' => 'Voice is not configured.']);
        }

        if ($brief->status !== BriefStatus::Completed) {
            throw ValidationException::withMessages(['audio' => 'Audio can only be created for a completed brief.']);
        }

        if (! in_array($brief->audio_status, [AudioStatus::None, AudioStatus::Failed], true)) {
            throw ValidationException::withMessages(['audio' => 'Audio is already being prepared or is ready.']);
        }

        $brief->forceFill(['audio_status' => AudioStatus::Queued])->save();
        GenerateBriefAudioJob::dispatch($brief->id);

        return to_route('briefs.show', $brief);
    }
}
