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
    ) {}
}
