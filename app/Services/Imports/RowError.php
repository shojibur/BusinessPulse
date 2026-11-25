<?php

namespace App\Services\Imports;

final readonly class RowError
{
    /**
     * @param  array<string, string>  $rawRow
     */
    public function __construct(
        public int $rowNumber,
        public ?string $column,
        public string $code,
        public string $message,
        public array $rawRow,
    ) {}
}
