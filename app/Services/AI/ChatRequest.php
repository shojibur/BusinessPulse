<?php

namespace App\Services\AI;

final readonly class ChatRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema  JSON schema of the structured answer
     */
    public function __construct(
        public array $messages,
        public array $schema,
        public int $briefId,
        public string $schemaName = 'brief_items',
        public ?int $timeoutSeconds = null,
        /** When true, model content that is not JSON yields empty data (the caller sends a corrective request) instead of an exception. */
        public bool $lenientContent = false,
    ) {}
}
