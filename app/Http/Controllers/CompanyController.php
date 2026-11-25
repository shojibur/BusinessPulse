<?php

namespace App\Http\Controllers;

use App\Services\CurrentCompany;
use App\Support\SettingsTabs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings -> Company. Read-only in PoV Core; the edit form is PoV Polish (task 8.8).
 */
class CompanyController extends Controller
{
    public function edit(Request $request, CurrentCompany $current): Response
    {
        $company = $current->resolve();
        abort_if($company === null, 404);

        Gate::authorize('view', $company);

        $isOwner = $request->user()->isOwner();

        return Inertia::render('settings/Company', [
            'settingsTabs' => SettingsTabs::for($request),
            'company' => [
                'name' => $company->name,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
                'is_demo' => $company->is_demo,
                'trading_days' => $company->trading_days,
                'reporting_cutoff_time' => $company->reporting_cutoff_time,
                'reporting_cutoff_lag_days' => $company->reporting_cutoff_lag_days,
                // Retention is shown to the Owner only (a Manager sees name, timezone and the calendar).
                'recording_retention_days' => $isOwner ? $company->recording_retention_days : null,
            ],
        ]);
    }
}
