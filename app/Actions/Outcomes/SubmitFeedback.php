<?php

namespace App\Actions\Outcomes;

use App\Events\Outcomes\FeedbackSubmitted;
use App\Models\Issue;
use App\Models\Outcome;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;

/** One upsert per user and target: a second vote replaces the first. Feedback never changes a rule, threshold or classification. */
final class SubmitFeedback
{
    public function __construct(private readonly BusinessClock $clock) {}

    public function handle(User $actor, Issue|Outcome $target, bool $useful, ?string $note): void
    {
        $note = $note === null || trim($note) === '' ? null : trim($note);
        $isOutcome = $target instanceof Outcome;
        $issue = $isOutcome ? $target->issue : $target;

        DB::transaction(function () use ($actor, $target, $useful, $note, $isOutcome, $issue): void {
            $row = $isOutcome
                ? DB::selectOne(
                    'INSERT INTO feedback (user_id, outcome_id, issue_id, useful, note, created_at, updated_at) VALUES (?, ?, NULL, ?, ?, now(), now())
                     ON CONFLICT (user_id, outcome_id) DO UPDATE SET useful = EXCLUDED.useful, note = EXCLUDED.note, updated_at = now()
                     RETURNING id, (xmax = 0) AS inserted',
                    [$actor->id, $target->id, $useful ? 'true' : 'false', $note],
                )
                : DB::selectOne(
                    'INSERT INTO feedback (user_id, outcome_id, issue_id, useful, note, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, now(), now())
                     ON CONFLICT (user_id, issue_id) WHERE outcome_id IS NULL DO UPDATE SET useful = EXCLUDED.useful, note = EXCLUDED.note, updated_at = now()
                     RETURNING id, (xmax = 0) AS inserted',
                    [$actor->id, $target->id, $useful ? 'true' : 'false', $note],
                );

            $companyId = (int) DB::table('departments')->where('id', $issue->department_id)->value('company_id');

            event(new FeedbackSubmitted(
                $isOutcome ? $target->id : null, $issue->id, $issue->department_id, $companyId, $actor->id, $this->clock->nowUtc(),
                $isOutcome ? 'outcome' : 'issue', $useful, $note !== null, ! (bool) $row->inserted,
            ));
        });
    }
}
