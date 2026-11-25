<?php

namespace App\Services\Imports;

final readonly class RowValidationResult
{
    /**
     * @param  list<RowError>  $errors
     */
    public function __construct(
        public int $rowNumber,
        public array $errors,
        public ?ValidRow $row,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
