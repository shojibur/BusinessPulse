<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SettingsTabs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings -> Staff. Read-only list in PoV Core; create, edit, deactivate and password reset are PoV Polish
 * (tasks 8.5 to 8.7). The list is always resolved through User::visibleTo so a Manager can never retrieve
 * users of another department.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::visibleTo($request->user())
            ->with(['department:id,name', 'manager:id,name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('settings/Staff', [
            'settingsTabs' => SettingsTabs::for($request),
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'role' => $user->role->value,
                'department' => $user->department?->name,
                'manager' => $user->manager?->name,
                'is_active' => $user->is_active,
            ])->values(),
        ]);
    }
}
