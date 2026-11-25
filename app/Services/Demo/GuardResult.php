<?php

namespace App\Services\Demo;

final readonly class GuardResult
{
    /** @param  list<string>  $failures */
    public function __construct(public array $failures) {}

    public function ok(): bool
    {
        return $this->failures === [];
    }
}
