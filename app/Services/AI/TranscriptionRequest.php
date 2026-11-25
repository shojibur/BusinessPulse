<?php

namespace App\Services\AI;

final readonly class TranscriptionRequest
{
    public function __construct(
        public int $meetingId,      // for the log line only
        public string $disk,
        public string $path,
        public string $uploadName,  // 'recording.mp3': never the user's original file name
        public string $mime,
        public string $model,
        public int $timeoutSeconds,
    ) {}
}
