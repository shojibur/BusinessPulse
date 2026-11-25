<?php

namespace App\Listeners\Intelligence;

use App\Actions\Intelligence\DispatchEvaluation;
use App\Enums\ImportStatus;
use App\Events\Imports\ImportCompleted;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queues an evaluation when an import completes. It never throws into the import: if queueing fails the
 * import stays completed, and the evaluation is recovered with "Refresh intelligence" (the Issues pages warn
 * when the latest evaluation is older than the latest completed import).
 */
class EvaluateAfterImportListener
{
    public function __construct(private readonly DispatchEvaluation $dispatch) {}

    public function handle(ImportCompleted $event): void
    {
        if (! in_array($event->status, [ImportStatus::Completed, ImportStatus::CompletedWithErrors], true)) {
            return;
        }

        try {
            $this->dispatch->handle($event->departmentId, $event->importId, null);
        } catch (Throwable $e) {
            Log::error('evaluation_dispatch_failed', ['import_id' => $event->importId, 'department_id' => $event->departmentId, 'exception' => $e::class]);
        }
    }
}
