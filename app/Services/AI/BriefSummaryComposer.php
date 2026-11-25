<?php

namespace App\Services\AI;

use App\Enums\CoverageStatus;
use App\Services\Intelligence\EvaluationHealth;
use Carbon\CarbonImmutable;

/**
 * Deterministic headline and limitation text from fixed templates. All figures come from the S03 WeekState and
 * Freshness values and the S04 evaluation health. No AI is involved, so this is also the text of every fallback.
 */
final class BriefSummaryComposer
{
    /**
     * @return array{headline: string, limitation: string|null, summary: string}
     */
    public function compose(int $itemCount, BriefContext $context): array
    {
        $status = $context->coverageStatus();
        $healthStatus = $context->health['status'];

        if (! $context->hasSalesData()) {
            $sentences = ['No sales data yet.'];
        } else {
            $trusted = ! in_array($healthStatus, [EvaluationHealth::FAILED, EvaluationHealth::PARTIAL, EvaluationHealth::NEVER], true);
            $sentences = [$itemCount === 0
                ? ($trusted ? 'No active issues were found.' : 'No active issues are recorded.')
                : $itemCount.($itemCount === 1 ? ' issue needs' : ' issues need').' attention.'];

            if ($context->freshness->state->value === 'behind') {
                $sentences[] = $this->overdueSentence($context);
                $sentences[] = 'Latest sales date is '.$context->freshness->latestSalesDate->format('Y-m-d').'.';
            } elseif ($status === CoverageStatus::Partial && ! $context->evaluationBlocksNormalBrief()) {
                $sentences[] = $this->partialWeekSentence($context);
            }

            $sentences = [...$sentences, ...$this->evaluationSentences($context)];
        }

        $headline = $sentences[0];
        $limitation = count($sentences) > 1 ? implode(' ', array_slice($sentences, 1)) : null;

        return ['headline' => $headline, 'limitation' => $limitation, 'summary' => implode(' ', $sentences)];
    }

    /** "21 Sep–27 Sep 2026" style label of two dates; one month collapses to "21–27 Sep 2026". */
    public static function range(CarbonImmutable|\DateTimeInterface|string $from, CarbonImmutable|\DateTimeInterface|string $to, bool $withYear = true): string
    {
        $year = $withYear ? ' Y' : '';
        $from = CarbonImmutable::parse($from);
        $to = CarbonImmutable::parse($to);

        if ($from->isSameDay($to)) {
            return $from->format('j M'.$year);
        }

        return $from->format('Y-m') === $to->format('Y-m')
            ? $from->format('j').'–'.$to->format('j M'.$year)
            : $from->format('j M').'–'.$to->format('j M'.$year);
    }

    private function overdueSentence(BriefContext $context): string
    {
        $f = $context->freshness;

        return 'Sales for '.self::range($f->overdueFrom, $f->overdueTo, false).' not yet received: '.$f->overdueBranchDays.' branch-days overdue.';
    }

    private function partialWeekSentence(BriefContext $context): string
    {
        $week = $context->week;
        $range = self::range($week->weekStart, $week->weekEnd);

        if ($week->completeness?->value === 'absent') {
            return "The latest closed week ({$range}) has no sales data; results cover incomplete data.";
        }

        $missing = max($week->expectedBranchDays - $week->presentBranchDays, 0);

        return "The latest closed week ({$range}) has {$missing} missing branch-days; results cover incomplete data.";
    }

    /**
     * @return list<string>
     */
    private function evaluationSentences(BriefContext $context): array
    {
        $health = $context->health;
        $at = $health['last_evaluated_at'] === null ? null
            : CarbonImmutable::parse($health['last_evaluated_at'])->setTimezone($context->timezone)->format('j M Y H:i');

        return match ($health['status']) {
            EvaluationHealth::NEVER => ['No evaluation has run yet.'],
            EvaluationHealth::FAILED => ['The last evaluation run failed, so these findings may be missing or out of date.'],
            EvaluationHealth::PARTIAL => ['The last evaluation could not evaluate '.$health['failed_rules'].' rule(s), so these findings may be incomplete.'],
            EvaluationHealth::STALE => $context->freshness->state->value === 'behind'
                ? ['Intelligence was last refreshed '.$at.'.']
                : ['Sales data or the reporting calendar changed after intelligence was last refreshed'.($at ? ' ('.$at.')' : '').'.'],
            default => $context->freshness->state->value === 'behind' && $at !== null
                ? ['Intelligence was last refreshed '.$at.'.'] : [],
        };
    }
}
