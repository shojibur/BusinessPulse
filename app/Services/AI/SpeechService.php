<?php

namespace App\Services\AI;

use App\Enums\AudioStatus;
use App\Models\Brief;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns the finished brief text into audio on the private local disk. The spoken text is the same text the Brief
 * page shows (BriefPresenter). The file name is random and never part of a public URL.
 */
final class SpeechService
{
    public function __construct(private readonly OpenAiClient $client, private readonly BriefPresenter $presenter) {}

    public function generate(Brief $brief): void
    {
        $audio = $this->client->speech(new SpeechRequest($brief->id, $this->presenter->spokenText($brief)));

        $path = 'briefs/audio/'.Str::random(40).'.mp3';
        Storage::disk('local')->put($path, $audio->bytes);

        $previous = $brief->audio_path;

        $brief->forceFill([
            'audio_path' => $path,
            'audio_model' => config('ai.speech_model'),
            'audio_voice' => config('ai.speech_voice'),
            'audio_status' => AudioStatus::Ready,
        ])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }
    }
}
