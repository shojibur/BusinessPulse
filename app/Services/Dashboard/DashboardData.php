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
        /** @var list<array<string, mixed>> open issues, high severity first (at most five) */
        public array $urgentIssues = [],
        /** @var array<string, int> task counts by status plus `total` (Owner: company, Manager: department) */
        public array $taskStatus = [],
        /** @var array<string, mixed>|null the latest outcome the user may see */
        public ?array $outcome = null,
        /** @var list<array<string, mixed>> the latest activity the user may see (at most five) */
        public array $activity = [],
        /** @var list<array{value: string, label: string, complete: bool, latest: bool}> closed weeks to choose from, newest first */
        public array $weekOptions = [],
        /** The past week being viewed (Y-m-d of its Monday), or null for the latest. */
        public ?string $viewingWeek = null,
        /** @var array<string, mixed>|null sales of one chosen day by branch; null when there is no sales data */
        public ?array $daily = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
