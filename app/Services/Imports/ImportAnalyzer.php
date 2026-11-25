<?php

namespace App\Services\Imports;

use Carbon\CarbonImmutable;

/**
 * Reads a stored CSV, validates every row, finds duplicate identities and classifies the valid rows.
 * Used at upload, when a preview is rebuilt, and again by the processing job.
 */
final class ImportAnalyzer
{
    public function __construct(
        private readonly CsvImportReader $reader,
        private readonly ImportRowValidator $validator,
        private readonly ImportClassifier $classifier,
    ) {}

    /**
     * @param  list<string>  $expectedBranches
     */
    public function analyze(string $path, int $departmentId, array $expectedBranches, CarbonImmutable $businessToday): ImportAnalysis
    {
        $total = 0;
        $valid = [];
        $rawByNumber = [];
        $errorsByRow = [];

        foreach ($this->reader->rows($path) as [$rowNumber, $cells, $positional]) {
            $total++;
            $result = $this->validator->validate($rowNumber, $cells, $expectedBranches, $businessToday, $positional);
            $rawByNumber[$rowNumber] = $cells ?? [];

            if ($result->isValid()) {
                $valid[$rowNumber] = $result->row;
            } else {
                $errorsByRow[$rowNumber] = $result->errors;
            }
        }

        foreach ($this->validator->detectDuplicateIdentities($valid, $rawByNumber) as $rowNumber => $errors) {
            $errorsByRow[$rowNumber] = $errors;
            unset($valid[$rowNumber]);
        }

        ksort($errorsByRow);
        $errors = [];
        foreach ($errorsByRow as $rowErrors) {
            array_push($errors, ...$rowErrors);
        }

        ksort($valid);

        return new ImportAnalysis(
            totalRows: $total,
            classified: $this->classifier->classify($departmentId, array_values($valid)),
            errors: $errors,
            invalidRows: count($errorsByRow),
        );
    }
}
