<?php

namespace App\Support;

use App\Models\Department;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Settings tabs, each shown only when the policy allows the user to view it.
 *
 * EXTENSION POINT: S03 adds the "Import template" tab and S04 the "Rules" tab by appending an entry
 * here (with its own policy check) and a page under resources/js/pages/settings. No placeholder
 * tabs are rendered for them.
 */
class SettingsTabs
{
    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function for(Request $request): array
    {
        $user = $request->user();
        $company = app(CurrentCompany::class)->resolve();

        if (! $user instanceof User || $company === null) {
            return [];
        }

        $gate = Gate::forUser($user);
        $tabs = [];

        if ($gate->allows('view', $company)) {
            $tabs[] = ['label' => 'Company', 'route' => 'settings.company', 'active' => 'settings.company'];
        }

        if ($gate->allows('viewAny', User::class)) {
            $tabs[] = ['label' => 'Staff', 'route' => 'settings.staff', 'active' => 'settings.staff'];
        }

        if ($gate->allows('viewAny', Department::class)) {
            $tabs[] = ['label' => 'Department', 'route' => 'settings.department', 'active' => 'settings.department'];
        }

        return array_map(fn (array $tab): array => [
            'label' => $tab['label'],
            'href' => route($tab['route'], absolute: false),
            'active' => $request->routeIs($tab['active']),
        ], $tabs);
    }
}
