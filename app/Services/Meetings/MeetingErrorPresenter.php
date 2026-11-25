<?php

namespace App\Services\Meetings;

use App\Enums\MeetingErrorCode;

/** Fixed user-facing text per error code. Provider text is never shown or stored. */
final class MeetingErrorPresenter
{
    public static function message(MeetingErrorCode|string|null $code): ?string
    {
        $code = is_string($code) ? MeetingErrorCode::tryFrom($code) : $code;

        return match ($code) {
            null => null,
            MeetingErrorCode::ProviderNotConfigured => 'Transcription is not configured. Paste the transcript instead.',
            MeetingErrorCode::RecordingMissing => 'The recording file could not be found. Paste the transcript instead.',
            MeetingErrorCode::RecordingDeleted => 'The recording was deleted, so it cannot be transcribed. Paste the transcript instead.',
            MeetingErrorCode::ConsentMissing => 'Recording consent is not recorded for this meeting, so the audio was not processed.',
            MeetingErrorCode::DepartmentUnavailable => 'The meeting department is no longer available. Contact an Owner.',
            MeetingErrorCode::EmptyTranscript => 'The transcript is empty or too short. Paste or edit the transcript, then try again.',
            MeetingErrorCode::TranscriptTooLong => 'The transcript is longer than 60,000 characters. Paste a shorter transcript instead.',
            MeetingErrorCode::Timeout => 'The AI service did not answer in time. Try again.',
            MeetingErrorCode::RateLimited => 'The AI service is busy. Try again in a few minutes.',
            MeetingErrorCode::ServerError => 'The AI service had a problem. Try again.',
            MeetingErrorCode::ClientError => 'The AI service rejected the request. Check the configuration, then try again.',
            MeetingErrorCode::InvalidResponse => 'The AI service returned an unusable answer. Try again.',
            MeetingErrorCode::InvalidAiOutput => 'The AI answer could not be validated, so no tasks were created. Try again.',
            MeetingErrorCode::TranscriptChanged => 'The transcript changed while tasks were being extracted, so the result was not saved. Try again.',
            MeetingErrorCode::Unexpected => 'Processing failed unexpectedly. Try again.',
        };
    }
}
