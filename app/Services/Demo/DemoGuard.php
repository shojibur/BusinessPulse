<?php

namespace App\Services\Demo;

use App\Models\Company;

/**
 * The single gate of every demo command. All conditions are evaluated independently so the operator sees every reason at
 * once. It never writes anything and never logs a company name, password or key.
 */
final class DemoGuard
{
    public function check(GuardContext $context): GuardResult
    {
        $failures = [];

        if (! config('demo.mode')) {
            $failures[] = 'Demo commands run only when DEMO_MODE=true.';
        }

        if (app()->environment('production') && ! config('demo.allow_production')) {
            $failures[] = 'Demo commands are disabled when APP_ENV=production (set DEMO_ALLOW_PRODUCTION_DEMO=true only on a dedicated demo server).';
        }

        if (Company::query()->where('is_demo', false)->exists()) {
            $failures[] = 'A company that is not marked as demo exists in this database. Nothing was changed.';
        }

        if ($context->command === 'reset') {
            $companies = Company::query()->get();

            if ($companies->count() !== 1 || ! $companies->first()->is_demo) {
                $failures[] = 'demo:reset needs exactly one company and it must be the demo company.';
            } elseif (! $context->force) {
                if (! $context->interactive) {
                    $failures[] = 'No interactive terminal is available; use --force to confirm.';
                } elseif ($context->typedName !== $companies->first()->name) {
                    $failures[] = 'The typed company name does not match the demo company.';
                }
            }
        } elseif (Company::query()->where('is_demo', true)->count() > 1) {
            $failures[] = 'More than one demo company exists.';
        }

        return new GuardResult($failures);
    }
}
