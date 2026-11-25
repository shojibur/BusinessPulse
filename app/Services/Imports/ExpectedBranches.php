<?php

namespace App\Services\Imports;

use App\Models\Department;

/**
 * The branch codes a department is expected to report, stored in departments.settings.expected_branches.
 * The list is configured (seeded or edited in Settings) and never derived from an uploaded file.
 */
final class ExpectedBranches
{
    /**
     * Sorted, unique, upper-case codes; empty when not configured.
     *
     * @return list<string>
     */
    public function get(Department $department): array
    {
        $codes = array_map(
            fn ($code) => strtoupper(trim((string) $code)),
            $department->expectedBranches(),
        );

        $codes = array_values(array_unique(array_filter($codes, fn (string $code) => $code !== '')));
        sort($codes);

        return $codes;
    }
}
