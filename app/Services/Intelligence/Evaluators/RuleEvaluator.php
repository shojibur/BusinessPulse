<?php

namespace App\Services\Intelligence\Evaluators;

use App\Enums\RuleKey;
use App\Models\BusinessRule;
use App\Models\Department;
use App\Services\Intelligence\EvaluationContext;
use App\Services\Intelligence\EvaluationOutcome;

/**
 * One evaluator per predefined rule. An evaluator is a pure read: it writes nothing and dispatches nothing.
 */
interface RuleEvaluator
{
    public function key(): RuleKey;

    public function evaluate(BusinessRule $rule, Department $department, EvaluationContext $context): EvaluationOutcome;
}
