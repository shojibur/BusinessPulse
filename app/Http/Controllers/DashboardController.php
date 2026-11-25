<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\Dashboard\DashboardMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Today. Owner and Manager get the executive figures; Staff get an empty state and NO data prop at all (the Staff
 * summary is added in S07). The shape is chosen here, so nothing about sales, issues or briefs can reach Staff.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->role === Role::Staff) {
            return redirect()->route('tasks.mine'); // the Staff landing page; no metric is touched
        }

        $department = $user->role === Role::Manager ? $user->department : $user->company->salesDepartment();

        if ($department === null) {
            return Inertia::render('dashboard/Index', ['audience' => 'owner_manager', 'data' => null]);
        }

        return Inertia::render('dashboard/Index', [
            'audience' => 'owner_manager',
            'data' => $this->metrics->forOwnerOrManager($user, $department)->toArray(),
            'canGenerateBrief' => true,
        ]);
    }
}
