<?php

namespace App\Services\AI;

use App\Models\Brief;
use App\Models\BriefItem;
use App\Services\Intelligence\MetricCalculator;

/**
 * The one source of brief text. The Brief page and the spoken audio are built from the same blocks, so what is
 * heard equals what is read. Figures are formatted here from the stored evidence copy (strings only).
 */
final class BriefPresenter
{
    public function __construct(private readonly MetricCalculator $metrics) {}

    /**
     * @return array{headline: string, limitation: string|null, items: list<array<string, mixed>>}
     */
    public function blocks(Brief $brief): array
    {
        $summary = (string) $brief->summary_text;
        $headline = (string) $brief->headline;
        $limitation = trim(substr($summary, strlen($headline))) ?: null;

        $items = [];
        foreach ($brief->items as $item) {
            $items[] = [
                'position' => $item->position,
                'issue_id' => $item->issue_id,
                'title' => $item->title,
                'explanation' => $item->explanation,
                'severity' => $item->severity,
                'period_label' => $item->period_label,
                'figures' => $this->figures($item) === $item->explanation ? null : $this->figures($item),
                'note' => $this->note($item),
            ];
        }

        return ['headline' => $headline, 'limitation' => $limitation, 'items' => $items];
    }

    public function displayText(Brief $brief): string
    {
        return $this->text($brief, ' ');
    }

    public function spokenText(Brief $brief): string
    {
        return $this->text($brief, ' ');
    }

    /**
     * The deterministic figures sentence of an item, from its latest-breach evidence.
     */
    public function figures(BriefItem $item): string
    {
        return $this->figuresFromEvidence($item->evidence, $item->period_label);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function figuresFromEvidence(array $evidence, string $periodLabel): string
    {
        $latest = $evidence['latest'];
        $pct = $latest['differencePct'] === null ? null : $this->metrics->display2((string) $latest['differencePct']);

        return match ($evidence['ruleKey']) {
            'weekly_revenue_decline' => 'Weekly revenue was '.$this->money($latest['currentValue']).' against '.$this->money($latest['comparisonValue'])
                .' the week before ('.$pct.'%) for '.$periodLabel.'.',
            'sales_target_shortfall' => 'Revenue was '.$this->money($latest['currentValue']).' against a target of '.$this->money($latest['comparisonValue'])
                .' ('.$pct.'% of target) for '.$periodLabel.'.',
            default => 'Sales data was received for '.(int) $latest['currentValue'].' of '.(int) $latest['comparisonValue']
                .' due branch-days ('.count($evidence['missingDueBranchDays']).' missing) for '.$periodLabel.'.',
        };
    }

    /**
     * Distinguishes the stored breach (history) from the present condition when a later evaluation no longer
     * shows it. Null while the condition still holds.
     */
    public function note(BriefItem $item): ?string
    {
        $current = $item->evidence['current'] ?? null;

        if ($current === null || $current['result'] === 'breached') {
            return null;
        }

        $label = BriefSummaryComposer::range($current['periodStart'], $current['periodEnd']);

        return match ($current['result']) {
            'not_breached' => "The most recent evaluation ({$label}) no longer shows this breach.",
            default => "The most recent evaluation ({$label}) could not be completed because data was insufficient.",
        };
    }

    private function text(Brief $brief, string $glue): string
    {
        $blocks = $this->blocks($brief);
        $parts = [$blocks['headline']];

        if ($blocks['limitation'] !== null) {
            $parts[] = $blocks['limitation'];
        }

        foreach ($blocks['items'] as $item) {
            $parts[] = $item['position'].'. '.$item['title'].'. '.$item['explanation'];
            if ($item['figures'] !== null) {
                $parts[] = $item['figures'];
            }

            if ($item['note'] !== null) {
                $parts[] = $item['note'];
            }
        }

        return implode($glue, $parts);
    }

    private function money(?string $value): string
    {
        return $this->metrics->formatMoney($value ?? '0');
    }
}
