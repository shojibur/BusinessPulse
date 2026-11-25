<?php

namespace App\Http\Controllers;

use App\Actions\Intelligence\DispatchEvaluation;
use App\Models\Department;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Refresh intelligence": queues an evaluation for a department with no triggering import. It is also the recovery
 * path when the evaluation after an import was not queued. Throttled to 6 per minute per user (route middleware).
 */
class RefreshIntelligenceController extends Controller
{
    public function __invoke(Request $request, DispatchEvaluation $dispatch): RedirectResponse
    {
        $user = $request->user();
        $requested = $request->input('department_id');

        // Resolved through the user's scope: another department's id is a 404, never a leak.
        $department = Department::visibleTo($user)
            ->when($requested !== null, fn ($query) => $query->whereKey((int) $requested))
            ->orderBy('id')
            ->firstOrFail();

        Gate::authorize('refresh-intelligence', $department);

        $dispatch->handle($department->id, null, $user->id);

        Flash::success('Refreshing intelligence. The results will update shortly.');

        return back();
    }
}
