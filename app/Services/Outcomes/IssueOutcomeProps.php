<?php

namespace App\Services\Outcomes;

use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Outcome;
use App\Models\User;
use App\Services\Activity\TraceTimelineBuilder;

/** The `outcomes`, `feedback` and `trace` props of the Issue detail page. Null for Staff. */
final class IssueOutcomeProps
{
    public function __construct(private readonly OutcomePresenter $presenter, private readonly TraceTimelineBuilder $trace) {}

    /**
     * @return array<string, mixed>|null
     */
    public function outcomes(Issue $issue, User $user): ?array
    {
        if ($user->isStaff()) {
            return null;
        }

        $timezone = $issue->department->company->timezone;
        $all = Outcome::query()->where('issue_id', $issue->id)->orderByDesc('evaluated_at')->orderByDesc('id')->get();

        if ($all->isEmpty()) {
            return null;
        }

        return [
            'heading' => OutcomeWording::HEADING,
            'standingSentence' => OutcomeWording::STANDING,
            'latest' => $this->presenter->summary($all->first(), $timezone),
            'earlier' => $all->slice(1, 4)->map(fn (Outcome $o) => [
                'id' => $o->id, 'resultLabel' => OutcomeWording::result($o->result), 'phaseLabel' => OutcomeWording::phase($o->phase),
                'evaluatedAtLabel' => $o->evaluated_at->setTimezone($timezone)->format('j M Y, H:i'), 'url' => route('outcomes.show', $o->id),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function feedback(Issue $issue, User $user): ?array
    {
        if ($user->isStaff()) {
            return null;
        }

        $rows = Feedback::query()->where('issue_id', $issue->id)->whereNull('outcome_id')->get();

        return [
            'mine' => $rows->firstWhere('user_id', $user->id)?->useful,
            'counts' => ['useful' => $rows->where('useful', true)->count(), 'notUseful' => $rows->where('useful', false)->count()],
            'submitUrl' => route('issues.feedback.store', $issue->id),
        ];
    }

    /**
     * @return array{stages: list<array<string, mixed>>, truncated: bool}|null
     */
    public function trace(Issue $issue, User $user): ?array
    {
        return $this->trace->build($issue, $user);
    }
}
