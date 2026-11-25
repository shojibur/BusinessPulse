<?php

namespace App\Enums;

/**
 * The closed set of Sanitized_Error codes stored in meetings.error_message. No provider text is ever stored.
 */
enum MeetingErrorCode: string
{
    case ProviderNotConfigured = 'provider_not_configured';
    case RecordingMissing = 'recording_missing';
    case RecordingDeleted = 'recording_deleted';
    case ConsentMissing = 'consent_missing';
    case DepartmentUnavailable = 'department_unavailable';
    case ActorUnauthorized = 'actor_unauthorized';
    case EmptyTranscript = 'empty_transcript';
    case TranscriptTooLong = 'transcript_too_long';
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case ServerError = 'server_error';
    case ClientError = 'client_error';
    case InvalidResponse = 'invalid_response';
    case InvalidAiOutput = 'invalid_ai_output';
    case TranscriptChanged = 'transcript_changed';
    case Unexpected = 'unexpected_error';

    /** Maps the fixed code of a provider exception; anything unknown is a client error. */
    public static function fromProvider(string $code): self
    {
        return self::tryFrom($code) ?? self::ClientError;
    }
}
