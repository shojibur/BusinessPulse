<?php

namespace App\Services\Intelligence;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Events\Intelligence\IssueCreated;
use App\Models\BusinessRule;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates, re-detects and recurs issues from a BREACHED evaluation. It never touches an issue for a
 * not_breached or insufficient_data result, never changes status, and never changes baseline_evaluation_id.
 * Call inside the transaction that stored the evaluation.
 */
final class IssueEngine
{
    public function __construct(private readonly UrgentIssueNotifier $notifier) {}

    /**
     * @param  bool  $reused  true when the evaluation already existed (same run key): nothing changes
     */
    public function applyBreach(BusinessRule $rule, RuleEvaluation $evaluation, bool $reused): ?Issue
    {
        if ($reused) {
            return null;
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $issue = $this->create($rule, $evaluation) ?? $this->redetect($rule, $evaluation);

            if ($issue !== null) {
                return $issue;
            }
        }

        // The open issue vanished between the failed insert and the re-read (resolved concurrently): skip.
        Log::warning('issue_engine_skipped', ['rule_id' => $rule->id, 'department_id' => $rule->department_id]);

        return null;
    }

    /**
     * Race-free insert: the partial unique index decides, never a prior SELECT.
     */
    private function create(BusinessRule $rule, RuleEvaluation $evaluation): ?Issue
    {
        $title = $rule->key->issueTitle();
        $recurrenceOf = Issue::query()
            ->where('business_rule_id', $rule->id)
            ->where('department_id', $rule->department_id)
            ->where('scope_key', Issue::SCOPE_KEY)
            ->where('status', IssueStatus::Resolved->value)
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->value('id');

        $at = $evaluation->evaluated_at;
        $history = json_encode([[
            'from' => null, 'to' => IssueStatus::Open->value, 'at' => $this->businessIso($evaluation),
            'by_user_id' => null, 'reason' => null, 'trigger' => 'created',
        ]]);
        $stamp = now()->format('Y-m-d H:i:s');

        $row = DB::selectOne(
            "INSERT INTO issues (business_rule_id, department_id, scope_key, recurrence_of_issue_id, title, severity,
                                 first_detected_at, last_detected_at, detection_count, period_start, period_end,
                                 baseline_evaluation_id, latest_evaluation_id, status, status_history, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 'open', ?::jsonb, ?, ?)
             ON CONFLICT (business_rule_id, department_id, scope_key) WHERE status <> 'resolved' DO NOTHING
             RETURNING id",
            [
                $rule->id, $rule->department_id, Issue::SCOPE_KEY, $recurrenceOf, $title, $evaluation->severity->value,
                $at->format('Y-m-d H:i:s'), $at->format('Y-m-d H:i:s'),
                $evaluation->period_start->format('Y-m-d'), $evaluation->period_end->format('Y-m-d'),
                $evaluation->id, $evaluation->id, $history, $stamp, $stamp,
            ],
        );

        if ($row === null) {
            return null;
        }

        $issue = Issue::query()->findOrFail($row->id);

        DB::afterCommit(fn () => IssueCreated::dispatch(
            $issue->department_id, $issue->id, $issue->severity->value, $issue->recurrence_of_issue_id,
        ));
        $this->notifier->maybeNotify($issue, $evaluation);

        return $issue;
    }

    /**
     * An open issue exists: update the detection fields only. Severity only ever rises; status, title,
     * period and baseline stay as they are.
     */
    private function redetect(BusinessRule $rule, RuleEvaluation $evaluation): ?Issue
    {
        $issue = Issue::query()
            ->where('business_rule_id', $rule->id)
            ->where('department_id', $rule->department_id)
            ->where('scope_key', Issue::SCOPE_KEY)
            ->where('status', '!=', IssueStatus::Resolved->value)
            ->lockForUpdate()
            ->first();

        if ($issue === null) {
            return null;
        }

        $previous = $issue->severity;
        $issue->severity = IssueSeverity::max($previous, $evaluation->severity);
        $issue->last_detected_at = $evaluation->evaluated_at;
        $issue->latest_evaluation_id = $evaluation->id;
        $issue->detection_count = $issue->detection_count + 1;
        $issue->status_history = [...$issue->status_history, [
            'from' => $issue->status->value, 'to' => $issue->status->value, 'at' => $this->businessIso($evaluation),
            'by_user_id' => null, 'reason' => null, 'trigger' => 're_detection',
        ]];
        $issue->save();

        // Raised to high by this detection: tell the Owner and Managers (deduplicated per user).
        if ($issue->severity === IssueSeverity::High && $previous !== IssueSeverity::High) {
            $this->notifier->maybeNotify($issue, $evaluation);
        }

        return $issue;
    }

    private function businessIso(RuleEvaluation $evaluation): string
    {
        $timezone = $evaluation->evidence['calendar']['timezone'] ?? config('business.timezone');

        return $evaluation->evaluated_at->setTimezone($timezone)->toIso8601String();
    }
}
