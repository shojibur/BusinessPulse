<?php

namespace App\Http\Controllers;

use App\Actions\Outcomes\SubmitFeedback;
use App\Http\Requests\SubmitFeedbackRequest;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Outcome;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FeedbackController extends Controller
{
    public function storeForOutcome(SubmitFeedbackRequest $request, string $outcome, SubmitFeedback $action): RedirectResponse
    {
        Gate::authorize('viewAny', Outcome::class); // Staff: 403
        $model = Outcome::query()->visibleTo($request->user())->find($outcome);
        abort_if($model === null, 404); // missing and out-of-scope are the same 404

        Gate::authorize('create', [Feedback::class, $model]);
        $action->handle($request->user(), $model, $request->boolean('useful'), $request->input('note'));
        Flash::success('Thanks, your feedback was recorded.');

        return back();
    }

    public function storeForIssue(SubmitFeedbackRequest $request, Issue $issue, SubmitFeedback $action): RedirectResponse
    {
        // `issue` comes from the scoped route binding (missing and out-of-scope ids: 404).
        Gate::authorize('viewAny', Outcome::class); // Staff: 403
        Gate::authorize('create', [Feedback::class, $issue]);
        $action->handle($request->user(), $issue, $request->boolean('useful'), $request->input('note'));
        Flash::success('Thanks, your feedback was recorded.');

        return back();
    }
}
