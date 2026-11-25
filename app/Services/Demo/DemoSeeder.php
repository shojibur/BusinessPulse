<?php

namespace App\Services\Demo;

use App\Models\BusinessRule;
use App\Models\Company;
use App\Models\Import;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Models\SalesRecord;
use App\Models\User;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the demonstration start state ONLY through the real paths: the S02 identity seeder, the S04 default rules, and the
 * S03 `demo:import` command (so S04 evaluates the history exactly as in production). It never inserts a business row
 * itself, and it never refreshes intelligence after the final clock change: the demo starts with week 8 overdue.
 */
class DemoSeeder
{
    public const HISTORY_CLOCK = '2026-09-21';

    public const START_CLOCK = '2026-09-28';

    public const TIME = '09:00';

    /** @throws DemoSeedException */
    public function seed(): SeedSummary
    {
        $company = Company::query()->where('is_demo', true)->first();

        if ($company !== null) {
            if ($this->alreadySeeded($company)) {
                return $this->summary($company, true);
            }

            if ($this->hasBusinessData($company)) {
                throw new DemoSeedException('The demo company already holds other data. Run: php artisan demo:reset --force');
            }
        }

        $password = (string) config('demo.password');

        if (mb_strlen($password) < 12) {
            throw new DemoSeedException('DEMO_PASSWORD must be set to at least 12 characters before seeding. No user was created.');
        }

        $previousQueue = config('queue.default');
        config(['queue.default' => 'sync']); // the import and evaluation run inline in this process

        try {
            $this->run('db:seed', ['--class' => DemoIdentitySeeder::class, '--force' => true]);
            $this->run('demo:set-date', ['date' => self::HISTORY_CLOCK, '--time' => self::TIME]);
            $this->run('demo:import', ['fixture' => 'history']);
            $this->verify();
            $this->run('demo:set-date', ['date' => self::START_CLOCK, '--time' => self::TIME]);
            // No "Refresh intelligence" and no evaluation after this point.
        } finally {
            config(['queue.default' => $previousQueue]);
        }

        Cache::forever('illuminate:queue:restart', time()); // workers pick up the fresh state

        return $this->summary(Company::query()->where('is_demo', true)->firstOrFail(), false);
    }

    /** @param  array<string, mixed>  $arguments */
    private function run(string $command, array $arguments): void
    {
        if (Artisan::call($command, $arguments) !== 0) {
            throw new DemoSeedException("{$command} failed: ".trim(preg_replace('/\s+/', ' ', Artisan::output()) ?? ''));
        }
    }

    private function alreadySeeded(Company $company): bool
    {
        $departmentIds = $company->departments()->pluck('id');
        $rule = BusinessRule::query()->whereIn('department_id', $departmentIds)->where('key', 'weekly_revenue_decline')->first();

        return User::query()->where('company_id', $company->id)->count() === 6
            && $rule !== null && (string) ($rule->params['high_drop_pct'] ?? '') === '15.00'
            && Import::query()->whereIn('department_id', $departmentIds)->count() === 1
            && Import::query()->whereIn('department_id', $departmentIds)->where('status', 'completed')->count() === 1
            && ! $this->hasBusinessData($company, ignoreHistory: true);
    }

    /** Any business data beyond the single history import and its evaluations. */
    private function hasBusinessData(Company $company, bool $ignoreHistory = false): bool
    {
        $departmentIds = $company->departments()->pluck('id');

        $extra = Issue::query()->whereIn('department_id', $departmentIds)->exists()
            || DB::table('briefs')->where('company_id', $company->id)->exists()
            || DB::table('meetings')->whereIn('department_id', $departmentIds)->exists()
            || DB::table('tasks')->whereIn('department_id', $departmentIds)->exists()
            || DB::table('activity_logs')->where('company_id', $company->id)->whereNotIn('action', ['import.completed'])->exists();

        if ($ignoreHistory) {
            return $extra;
        }

        return $extra || Import::query()->whereIn('department_id', $departmentIds)->exists() || SalesRecord::query()->whereIn('department_id', $departmentIds)->exists();
    }

    /** The seeded state must equal what docs/demo-scenario.md and expected.json describe. */
    private function verify(): void
    {
        $expected = json_decode((string) file_get_contents(base_path('database/demo/expected.json')), true, 512, JSON_THROW_ON_ERROR)['fixtures']['history'];
        $errors = [];
        $active = SalesRecord::query()->where('is_active', true)->count();

        if ($active !== 126) {
            $errors[] = "expected 126 active sales records, found {$active}";
        }

        if (Issue::query()->exists()) {
            $errors[] = 'an issue exists after the history import';
        }

        foreach ($expected['evaluations'] as $e) {
            if (($e['trigger'] ?? '') !== 'import') {
                continue;
            }

            $evaluation = RuleEvaluation::query()->whereHas('rule', fn ($q) => $q->where('key', $e['rule']))->orderByDesc('id')->first();

            if ($evaluation === null || $evaluation->result->value !== $e['result']) {
                $errors[] = "evaluation of {$e['rule']} differs from expected.json";
            }
        }

        if ($errors !== []) {
            throw new DemoSeedException('The seeded state does not match expected.json: '.implode('; ', $errors).'.');
        }
    }

    private function summary(Company $company, bool $already): SeedSummary
    {
        $departmentIds = $company->departments()->pluck('id');
        $import = Import::query()->whereIn('department_id', $departmentIds)->orderBy('id')->first();
        $clockFile = (string) config('demo.clock_file');

        return new SeedSummary(
            $already,
            User::query()->where('company_id', $company->id)->count(),
            'weekly_revenue_decline high_drop_pct = 15.00',
            $import === null ? [] : ['inserted' => (int) $import->inserted_rows, 'corrected' => (int) $import->corrected_rows, 'unchanged' => (int) $import->unchanged_rows, 'invalid' => (int) $import->invalid_rows],
            trim((string) (Storage::disk('local')->exists($clockFile) ? Storage::disk('local')->get($clockFile) : 'real clock')),
        );
    }
}
