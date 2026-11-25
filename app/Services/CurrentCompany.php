<?php

namespace App\Services;

use App\Models\Company;

/**
 * The single Company of Stage 1, resolved once per request (bound as a scoped singleton).
 * Returns null before `identity:create-owner` or the demo seeder has created the company.
 */
class CurrentCompany
{
    private ?Company $company = null;

    private bool $resolved = false;

    public function resolve(): ?Company
    {
        if (! $this->resolved) {
            $this->company = Company::query()->orderBy('id')->first();
            $this->resolved = true;
        }

        return $this->company;
    }

    /**
     * Drop the memoized company (after the profile changes within the same process).
     */
    public function forget(): void
    {
        $this->company = null;
        $this->resolved = false;
    }
}
