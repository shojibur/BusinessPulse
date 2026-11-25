<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Department;
use App\Support\SettingsTabs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings -> Department. Read-only. Departments are never created, renamed or deleted from the UI.
 */
class DepartmentController extends Controller
{
    public function show(Request $request, ?Department $department = null): Response
    {
        $user = $request->user();

        Gate::authorize('viewAny', Department::class);

        // The route parameter is bound through Department::visibleTo (404 when out of scope). Without a
        // parameter, show the first department the user may see; a user without one sees an empty state.
        $department ??= Department::visibleTo($user)->orderBy('id')->first();

        if ($department !== null) {
            Gate::authorize('view', $department);
        }

        return Inertia::render('settings/Department', [
            'settingsTabs' => SettingsTabs::for($request),
            'department' => $department === null ? null : $this->present($department),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Department $department): array
    {
        $members = $department->users()->orderBy('name')->get(['id', 'name', 'job_title', 'role', 'is_active']);
        $staff = $members->filter(fn ($member) => $member->role === Role::Staff && $member->is_active);

        return [
            'id' => $department->id,
            'name' => $department->name,
            'expected_branches' => $department->expectedBranches(),
            'managers' => $members
                ->filter(fn ($member) => $member->role === Role::Manager && $member->is_active)
                ->map(fn ($member) => ['id' => $member->id, 'name' => $member->name])
                ->values(),
            'active_staff_count' => $staff->count(),
            'staff' => $staff
                ->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'job_title' => $member->job_title])
                ->values(),
        ];
    }
}
