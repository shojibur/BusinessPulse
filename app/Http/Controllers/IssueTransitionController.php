<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransitionIssueRequest;
use App\Models\Issue;
use App\Services\Intelligence\IssueLifecycle;
use App\Services\Intelligence\IssueTransitionNotAllowedException;
use App\Services\Intelligence\TransitionResult;
use App\Support\Flash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Manual lifecycle actions for an Owner or a Manager in scope. The issue is resolved through
 * Issue::visibleTo (404 outside the department) and authorized by IssuePolicy (403 for Staff).
 */
class IssueTransitionController extends Controller
{
    public function acknowledge(Issue $issue, TransitionIssueRequest $request, IssueLifecycle $lifecycle): RedirectResponse|JsonResponse
    {
        Gate::authorize('transition', $issue);

        return $this->respond($request, $issue, fn () => $lifecycle->acknowledge($issue, $request->user()), 'Issue acknowledged.');
    }

    public function startMonitoring(Issue $issue, TransitionIssueRequest $request, IssueLifecycle $lifecycle): RedirectResponse|JsonResponse
    {
        Gate::authorize('transition', $issue);

        return $this->respond($request, $issue, fn () => $lifecycle->startMonitoring($issue, $request->user(), $request->input('reason')), 'Monitoring started.');
    }

    public function resolve(Issue $issue, TransitionIssueRequest $request, IssueLifecycle $lifecycle): RedirectResponse|JsonResponse
    {
        Gate::authorize('transition', $issue);

        return $this->respond($request, $issue, fn () => $lifecycle->resolveManual($issue, $request->user(), $request->input('reason')), 'Issue resolved.');
    }

    /**
     * @param  callable(): TransitionResult  $transition
     */
    private function respond(TransitionIssueRequest $request, Issue $issue, callable $transition, string $success): RedirectResponse|JsonResponse
    {
        try {
            $result = $transition();
        } catch (IssueTransitionNotAllowedException $e) {
            // A disallowed transition is a 422 and the issue is unchanged.
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => $result->issue->status->value, 'changed' => $result->changed]);
        }

        // A repeated submit finds the work already done: 200/redirect without a second change.
        $result->changed ? Flash::success($success) : Flash::toast('info', 'This issue was already updated.');

        return redirect()->route('issues.show', $issue);
    }
}
