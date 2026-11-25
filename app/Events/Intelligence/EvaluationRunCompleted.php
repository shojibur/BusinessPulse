<?php

namespace App\Events\Intelligence;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * One evaluation run finished with at least one rule processed. Carries no figures or text: its consumer
 * (S08's OutcomeVerifier) reads the stored evaluations. `evaluationIds` are sorted ascending, unique, never empty,
 * and include evaluations that were reused as well as newly inserted ones.
 */
final class EvaluationRunCompleted extends IntelligenceEvent implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<int>  $evaluationIds
     */
    public function __construct(
        int $departmentId,
        public readonly ?int $importId,
        ?int $actorUserId,
        public readonly array $evaluationIds,
    ) {
        parent::__construct($departmentId, $actorUserId);
    }
}
