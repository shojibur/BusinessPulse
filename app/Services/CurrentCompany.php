<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The company of the signed-in company user, resolved once per request (bound as a scoped singleton). With nobody
 * signed in (queue jobs, Artisan commands, the demo tooling) it falls back to the oldest company, which is the single
 * demo or first company; code that works for a specific company passes that company's timezone explicitly.
 * Returns null before `identity:create-owner`, the demo seeder or the platform panel has created a company.
 */
class CurrentCompany
{
    private ?Company $company = null;

    private bool $resolved = false;

    private int|string|null $resolvedFor = null;

    public function resolve(): ?Company
    {
        // The web guard only: a platform admin has no company and must never resolve one.
        $user = Auth::guard('web')->user();
        $userId = $user instanceof User ? $user->id : null;

        if (! $this->resolved || $this->resolvedFor !== $userId) {
            $this->company = $userId !== null
                ? Company::query()->find($user->company_id)
                : Company::query()->orderBy('id')->first();
            $this->resolved = true;
            $this->resolvedFor = $userId;
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
        $this->resolvedFor = null;
    }
}
