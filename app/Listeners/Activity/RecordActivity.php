<?php

namespace App\Listeners\Activity;

use App\Models\Department;
use App\Services\Activity\ActivityEntry;
use App\Services\Activity\ActivityMap;
use App\Services\Activity\ActivityRecorder;
use App\Services\Activity\SubjectDepartmentResolver;
use App\Services\Demo\DemoResetFlag;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued listener (queue `default`, 3 tries, backoff 10/30/60 s) that writes the activity rows of ONE event through the
 * recorder using the declarative ActivityMap. It runs after the producing action has committed and in its own job, so a
 * failure here can neither roll back nor report a failure for the business action. One INSERT per row, so a retry does not
 * duplicate rows. Registered explicitly per event in OutcomesServiceProvider (not auto-discovered).
 */
class RecordActivity implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(object $event): void
    {
        // A demo reset is deleting this company's data: do nothing (RF-01).
        if (app(DemoResetFlag::class)->isActive()) {
            return;
        }
        $spec = ActivityMap::core()[$event::class] ?? null;

        if ($spec === null) {
            return;
        }

        $subjectId = (int) ($spec['subjectId'])($event);
        $departmentId = property_exists($event, 'departmentId') ? $event->departmentId : app(SubjectDepartmentResolver::class)->resolve($spec['subject'], $subjectId);
        $companyId = property_exists($event, 'companyId') ? $event->companyId : (int) Department::query()->whereKey($departmentId)->value('company_id');
        $issueIds = isset($spec['issueIds']) ? ($spec['issueIds'])($event) : [];
        $properties = isset($spec['properties']) ? ($spec['properties'])($event) : [];
        $recorder = app(ActivityRecorder::class);

        // Meeting events: one row per linked issue (or one row with a null issue when none is linked).
        foreach ($issueIds === [] ? [null] : $issueIds as $issueId) {
            $recorder->record(new ActivityEntry(
                $companyId, $departmentId, $event->actorUserId ?? null, $spec['action'], $spec['subject'], $subjectId,
                $issueId, $properties, $event->occurredAt,
            ));
        }
    }

    public function failed(object $event, Throwable $exception): void
    {
        Log::warning('activity_listener_failed', ['listener' => static::class, 'event' => $event::class, 'exception' => $exception::class]);
    }
}
