<?php

namespace App\Services\Dashboard;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The explicit Inertia payload of the executive dashboard. Strings, integers and nulls only.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class DashboardData implements Arrayable
{
    /**
     * @param  array<string, mixed>  $revenue
     * @param  array<string, mixed>  $change
     * @param  array<string, mixed>  $targetAchievement
     * @param  array<string, mixed>  $freshness
     * @param  array<string, mixed>  $evaluation
     * @param  list<array<string, mixed>>  $revenueVsTarget
     * @param  array<string, mixed>|null  $latestBrief
     */
    public function __construct(
        public string $departmentName,
        public array $revenue,
        public array $change,
        public array $targetAchievement,
        public array $freshness,
        public array $evaluation,
        public ?string $coverageNotice,
        public int $openIssueCount,
        public int $highPriorityIssueCount,
        public array $revenueVsTarget,
        public ?array $latestBrief,
        /** @var array{open: int, overdue: int} persisted task counts (Owner: company, Manager: department) */
        public array $taskCounts = ['open' => 0, 'overdue' => 0],
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
