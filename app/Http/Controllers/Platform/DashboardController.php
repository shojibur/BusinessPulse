<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformAuditLog;
use App\Services\AI\AiEndpoint;
use App\Services\AI\AiSettings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform overview (S10/S11): company counts, the newest companies, whether the three AI jobs are ready and the
 * latest platform actions. Counts and names only: never a company's business data.
 */
class DashboardController extends Controller
{
    public function __invoke(AiSettings $settings): Response
    {
        $companies = Company::query()->withCount('users')->orderByDesc('id')->get();

        return Inertia::render('platform/Dashboard', [
            'stats' => [
                'companies' => $companies->count(),
                'active' => $companies->filter(fn (Company $c) => ! $c->isSuspended())->count(),
                'suspended' => $companies->filter(fn (Company $c) => $c->isSuspended())->count(),
                'demo' => $companies->where('is_demo', true)->count(),
                'users' => (int) $companies->sum('users_count'),
            ],
            'recent' => $companies->take(5)->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'is_demo' => $company->is_demo,
                'suspended' => $company->isSuspended(),
                'users_count' => $company->users_count,
                'created_at' => $company->created_at?->toIso8601String(),
            ])->values()->all(),
            'ai' => [
                ['key' => 'text', 'label' => 'Text', ...$this->status($settings->text())],
                ['key' => 'transcription', 'label' => 'Transcription', ...$this->status($settings->transcription())],
                ['key' => 'speech', 'label' => 'Voice', ...$this->status($settings->speech())],
            ],
            'activity' => PlatformAuditLog::query()->with(['admin:id,name', 'company:id,name'])->orderByDesc('id')->limit(8)->get()
                ->map(fn (PlatformAuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'by' => $log->admin?->name,
                    'company' => $log->company?->name,
                    'at' => $log->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    /** @return array{configured: bool, source: string, provider: string, model: string|null} */
    private function status(AiEndpoint $endpoint): array
    {
        return ['configured' => $endpoint->configured(), 'source' => $endpoint->source, 'provider' => $endpoint->provider, 'model' => $endpoint->model];
    }
}
