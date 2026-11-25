<?php

namespace App\Services\Intelligence;

use App\Enums\IssueSeverity;
use App\Enums\RuleKey;
use App\Events\Intelligence\UrgentIssueNotified;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Models\User;
use App\Notifications\UrgentIssueNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * In-app notifications for high-severity issues: the company Owner and the active Managers of the issue's
 * department. Deduplicated per user by `issue_id:severity`. Created in the caller's transaction so the
 * notification appears with the issue; the event is dispatched after commit. No email, SMS or push.
 */
final class UrgentIssueNotifier
{
    public function __construct(private readonly MetricCalculator $metrics) {}

    /**
     * @return list<int> ids of the users who received a new notification
     */
    public function maybeNotify(Issue $issue, RuleEvaluation $evaluation): array
    {
        if ($issue->severity !== IssueSeverity::High) {
            return [];
        }

        $issue->loadMissing(['department', 'rule']);
        $dedupeKey = "{$issue->id}:{$issue->severity->value}";

        $payload = [
            'dedupe_key' => $dedupeKey,
            'issue_id' => $issue->id,
            'title' => $issue->title,
            'severity' => $issue->severity->value,
            'department_name' => $issue->department->name,
            'summary_line' => $this->summaryLine($issue->rule->key, $evaluation),
            'url' => route('issues.show', $issue, absolute: false),
        ];

        $notified = [];
        foreach ($this->recipients($issue) as $user) {
            $alreadyNotified = DB::table('notifications')
                ->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $user->id)
                ->whereRaw("data->>'dedupe_key' = ?", [$dedupeKey])
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $user->notify(new UrgentIssueNotification($payload));
            $notified[] = $user->id;
        }

        if ($notified !== []) {
            DB::afterCommit(fn () => UrgentIssueNotified::dispatch(
                $issue->department_id, $issue->id, $issue->severity->value, $dedupeKey, $notified,
            ));
        }

        return $notified;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Issue $issue)
    {
        $companyId = $issue->department->company_id;

        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->where('role', 'owner')
                ->orWhere(fn ($managers) => $managers->where('role', 'manager')->where('department_id', $issue->department_id)))
            ->orderBy('id')
            ->get();
    }

    private function summaryLine(RuleKey $key, RuleEvaluation $evaluation): string
    {
        $pct = $evaluation->difference_pct === null ? null : $this->metrics->display2((string) $evaluation->difference_pct);

        return match ($key) {
            RuleKey::WeeklyRevenueDecline => sprintf(
                'Revenue %s %s%% (%s vs %s)',
                bccomp((string) $evaluation->difference_pct, '0', 4) < 0 ? 'fell' : 'changed',
                ltrim((string) $pct, '-'),
                $this->metrics->formatMoney((string) $evaluation->current_value),
                $this->metrics->formatMoney((string) $evaluation->comparison_value),
            ),
            RuleKey::SalesTargetShortfall => sprintf(
                'Target achievement %s%% (%s of %s)',
                $pct,
                $this->metrics->formatMoney((string) $evaluation->current_value),
                $this->metrics->formatMoney((string) $evaluation->comparison_value),
            ),
            RuleKey::MissingSalesData => sprintf(
                '%d of %d due branch-days received',
                (int) $evaluation->current_value,
                (int) $evaluation->comparison_value,
            ),
        };
    }
}
