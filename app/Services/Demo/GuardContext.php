<?php

namespace App\Services\Demo;

final readonly class GuardContext
{
    public function __construct(
        public string $command,        // 'seed' or 'reset'
        public bool $force = false,
        public bool $interactive = false,
        public ?string $typedName = null,
    ) {}
}
