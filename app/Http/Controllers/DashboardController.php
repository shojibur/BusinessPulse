<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\AI\OpenAiClient;
use App\Services\Dashboard\DashboardMetrics;
use App\Services\Imports\ReportingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Today. Owner and Manager get the executive figures; Staff get an empty state and NO data prop at all (the Staff
 * summary is added in S07). The shape is chosen here, so nothing about sales, issues or briefs can reach Staff.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->role === Role::Staff) {
            return redirect()->route('tasks.mine'); // the Staff landing page; no metric is touched
        }

        $department = $user->role === Role::Manager ? $user->department : $user->company->salesDepartment();

        if ($department === null) {
            return Inertia::render('dashboard/Index', ['audience' => 'owner_manager', 'data' => null]);
        }

        return Inertia::render('dashboard/Index', [
            'audience' => 'owner_manager',
            'data' => $this->metrics->forOwnerOrManager($user, $department, $this->requestedWeek($request), $this->requestedDay($request))->toArray(),
            'canGenerateBrief' => true,
            'ai' => ['text' => OpenAiClient::textConfigured()],
        ]);
    }

    /** The Monday of the week in `?week=YYYY-MM-DD`, or null when absent or not a valid date (the latest week is shown). */
    private function requestedWeek(Request $request): ?CarbonImmutable
    {
        $value = $request->query('week');

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return $date === false || $date->format('Y-m-d') !== $value ? null : ReportingCalendar::weekStart($date);
    }

    /** The date in `?day=YYYY-MM-DD`, or null when absent or invalid (the latest sales day is shown). */
    private function requestedDay(Request $request): ?CarbonImmutable
    {
        $value = $request->query('day');

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

        return $date === false || $date->format('Y-m-d') !== $value ? null : $date;
    }
}
