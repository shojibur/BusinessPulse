<?php

namespace App\Services\Intelligence;

use App\Models\Issue;
use App\Models\RuleEvaluation;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The authoritative, read-only evidence of an issue for briefs (S05) and outcomes (S08). Every figure comes from
 * the STORED evaluations and their evidence snapshots; sales_records are never read, so nothing is recomputed or
 * invented. Serializes to strings and integers only (decimal strings for money and percentages).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class IssueEvidence implements Arrayable
{
    /**
     * @param  list<array{branch_code: string, date: string, kind: string}>  $missingDueBranchDays
     * @param  list<int>  $importIds
     */
    public function __construct(
        public int $issueId,
        public string $title,
        public string $severity,
        public string $status,
        public string $ruleKey,
        public EvaluationSummary $baseline,
        public EvaluationSummary $latest,
        public string $primaryMetricName,
        public ?string $baselinePrimaryValue,
        public ?string $latestPrimaryValue,
        public array $missingDueBranchDays,
        public array $importIds,
        public ?EvaluationSummary $current = null,
    ) {}

    /**
     * Whether the most recent evaluation of the rule still shows the breach. `latest` is the latest BREACH the issue
     * recorded (history); `current` is the latest evaluation whatever its result (the present condition).
     */
    public function conditionStillBreached(): bool
    {
        return $this->current === null || $this->current->result === 'breached';
    }

    public static function forIssue(Issue $issue): self
    {
        $issue->loadMissing(['rule', 'baselineEvaluation', 'latestEvaluation']);
        $latest = $issue->latestEvaluation;
        $current = RuleEvaluation::query()
            ->where('business_rule_id', $issue->business_rule_id)
            ->where('department_id', $issue->department_id)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->first();
        $evidence = $latest->evidence;

        // The missing branch-days of the evaluated period (window for missing data, current week otherwise).
        $coverage = $evidence['coverage'] ?? [];
        $missing = $coverage['missingDueBranchDays'] ?? ($coverage['current']['missingDueBranchDays'] ?? []);

        return new self(
            $issue->id,
            $issue->title,
            $issue->severity->value,
            $issue->status->value,
            $issue->rule->key->value,
            EvaluationSummary::fromEvaluation($issue->baselineEvaluation),
            EvaluationSummary::fromEvaluation($latest),
            $issue->rule->key->metricName(),
            $issue->baselineEvaluation->difference_pct,
            $latest->difference_pct,
            array_values($missing),
            array_values(array_map('intval', $evidence['contributingImportIds'] ?? [])),
            $current === null ? null : EvaluationSummary::fromEvaluation($current),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'issueId' => $this->issueId,
            'title' => $this->title,
            'severity' => $this->severity,
            'status' => $this->status,
            'ruleKey' => $this->ruleKey,
            'baseline' => $this->baseline->toArray(),
            'latest' => $this->latest->toArray(),
            'primaryMetricName' => $this->primaryMetricName,
            'baselinePrimaryValue' => $this->baselinePrimaryValue,
            'latestPrimaryValue' => $this->latestPrimaryValue,
            'missingDueBranchDays' => $this->missingDueBranchDays,
            'importIds' => $this->importIds,
            'current' => $this->current?->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['issueId'], $data['title'], $data['severity'], $data['status'], $data['ruleKey'],
            EvaluationSummary::fromArray($data['baseline']), EvaluationSummary::fromArray($data['latest']),
            $data['primaryMetricName'], $data['baselinePrimaryValue'], $data['latestPrimaryValue'],
            $data['missingDueBranchDays'], array_map('intval', $data['importIds']),
            isset($data['current']) ? EvaluationSummary::fromArray($data['current']) : null,
        );
    }
}
