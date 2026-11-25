<?php

namespace App\Services\AI;

final readonly class SpeechRequest
{
    public function __construct(public int $briefId, public string $text) {}
}
