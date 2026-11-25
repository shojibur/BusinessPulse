<?php

namespace App\Services\AI;

use App\Enums\MeetingErrorCode;
use App\Models\Meeting;
use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;
use App\Services\Meetings\MeetingStageException;

/**
 * Sends the recording to the provider and returns the transcript text. Recording existence, consent and deletion were
 * already checked by MeetingRunGuard. Never invents text: any failure is a coded exception.
 */
final class TranscriptionService
{
    public const MAX_CHARACTERS = 60000;

    public function __construct(private readonly OpenAiClient $client) {}

    /**
     * @throws RecoverableProviderException when the queue should retry
     * @throws MeetingStageException for every terminal failure
     */
    public function transcribe(Meeting $meeting): string
    {
        $model = config('ai.transcription_model');

        if (! is_string($model) || trim($model) === '' || ! config('ai.api_key') || ! config('ai.base_url')) {
            throw new MeetingStageException(MeetingErrorCode::ProviderNotConfigured);
        }

        try {
            $response = $this->client->transcribe(new TranscriptionRequest(
                meetingId: $meeting->id,
                disk: 'local',
                path: (string) $meeting->recording_path,
                uploadName: 'recording.'.pathinfo((string) $meeting->recording_path, PATHINFO_EXTENSION),
                mime: (string) $meeting->recording_mime,
                model: $model,
                timeoutSeconds: (int) config('ai.timeouts.transcription', 120),
            ));
        } catch (RecoverableProviderException $e) {
            throw $e; // the job decides: retry or final failure
        } catch (ProviderException $e) {
            throw new MeetingStageException(MeetingErrorCode::fromProvider($e->errorCode()));
        }

        $text = trim(str_replace("\r\n", "\n", $response->text));

        if ($text === '') {
            throw new MeetingStageException(MeetingErrorCode::EmptyTranscript);
        }

        if (mb_strlen($text) > self::MAX_CHARACTERS) {
            throw new MeetingStageException(MeetingErrorCode::TranscriptTooLong);
        }

        return $text;
    }
}
