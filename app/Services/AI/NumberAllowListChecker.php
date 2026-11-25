<?php

namespace App\Services\AI;

/**
 * Guards AI wording about an issue. A numeric allow-list cannot prove that a sentence is true (valid numbers can be
 * attached to a wrong claim), so the rule is stricter: AI wording may contain NO numerals at all. Every figure the
 * reader sees (value, direction, unit and reporting period) is rendered deterministically by BriefPresenter from the
 * stored evaluation. The wording is additionally rejected when it contradicts the evidence direction (every Stage 1
 * breach is a decline, shortfall or missing data), confuses revenue with its target, or states a cause.
 */
final class NumberAllowListChecker
{
    /** Movement words that claim an increase or an improvement. */
    private const POSITIVE = '/\b(rose|risen|rising|rise|increase[sd]?|increasing|grew|grow|grown|growth|gain(?:ed|s)?|improv\w*|recover\w*|surg\w*|jump\w*|higher|up|exceed\w*|surpass\w*|beat|beating|outperform\w*|ahead|strong(?:er)?|record)\b/i';

    /** Revenue claimed to have met or passed its target. */
    private const TARGET_MET = '/\b(met|meet[s]?|meeting|hit|hits|reach(?:ed|es)?|achiev\w*|above|over|beyond|on)\b[^.]{0,30}\btarget\b/i';

    /** Causal or forward-looking claims the evidence cannot support. */
    private const UNSUPPORTED = '/\b(because|due to|caused|causing|as a result|result of|driven by|led to|leading to|thanks to|will|forecast\w*|expected to|should)\b/i';

    /**
     * @param  array<string, mixed>  $evidence  the item's IssueEvidence array
     */
    public function passes(string $wording, array $evidence): bool
    {
        return $this->violation($wording, $evidence) === null;
    }

    /**
     * The first rule the wording breaks, or null. Fixed strings, never echoing the wording.
     *
     * @param  array<string, mixed>  $evidence
     */
    public function violation(string $wording, array $evidence): ?string
    {
        if (preg_match('/\d/u', $wording) === 1 || preg_match('/%/u', $wording) === 1) {
            return 'it contained a number or percentage (figures are added by the application)';
        }

        if (preg_match(self::POSITIVE, $wording) === 1) {
            return 'it described an increase or improvement, but the evidence shows a decline or shortfall';
        }

        if (($evidence['ruleKey'] ?? null) === 'sales_target_shortfall' && preg_match(self::TARGET_MET, $wording) === 1) {
            return 'it claimed the target was met, but revenue is below target';
        }

        if (preg_match(self::UNSUPPORTED, $wording) === 1) {
            return 'it stated a cause or a prediction that the evidence does not support';
        }

        return null;
    }
}
