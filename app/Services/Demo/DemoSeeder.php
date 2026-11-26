<?php

namespace App\Services\Demo;

use App\Enums\MeetingStatus;
use App\Events\Meetings\MeetingCreated;
use App\Models\BusinessRule;
use App\Models\Company;
use App\Models\Import;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\RuleEvaluation;
use App\Models\SalesRecord;
use App\Models\User;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Support\Facades\Artisan;
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

    public const MEETING_TITLE = 'Sales recovery huddle';

    /**
     * @param  bool  $populated  also import the week 8 decline through the real workflow, so the demo company has an open
     *                           high issue, urgent notifications and a draft meeting. Without it the demo starts clean
     *                           (week 8 overdue) as the presenter walkthrough needs.
     *
     * @throws DemoSeedException
     */
    public function seed(bool $populated = false, bool $full = false): SeedSummary
    {
        $populated = $populated || $full;
        $company = Company::query()->where('is_demo', true)->first();
        $startFromClean = false;
        $startFromPopulated = false;

        if ($company !== null) {
            if ($full && $this->alreadyFull($company)) {
                return $this->summary($company, true, true);
            }

            if ($full && $this->alreadyPopulated($company)) {
                $startFromPopulated = true; // only the story after week 8 is missing
            } elseif (! $full && $populated && $this->alreadyPopulated($company)) {
                return $this->summary($company, true, true);
            } elseif ($this->alreadySeeded($company)) {
                if (! $populated) {
                    return $this->summary($company, true);
                }

                $startFromClean = true; // the clean start state exists: only the week 8 step is missing
            } elseif ($this->hasBusinessData($company)) {
                throw new DemoSeedException('The demo company already holds other data. Run: php artisan demo:reset --force');
            }
        }

        $password = (string) config('demo.password');

        if (mb_strlen($password) < 8) {
            throw new DemoSeedException('DEMO_PASSWORD must be set to at least 8 characters before seeding. No user was created.');
        }

        $previousQueue = config('queue.default');
        config(['queue.default' => 'sync']); // the import and evaluation run inline in this process

        try {
            if (! $startFromClean) {
                $this->run('db:seed', ['--class' => DemoIdentitySeeder::class, '--force' => true]);
                $this->run('demo:set-date', ['date' => self::HISTORY_CLOCK, '--time' => self::TIME]);
                $this->run('demo:import', ['fixture' => 'history']);
                $this->verify();
                $this->run('demo:set-date', ['date' => self::START_CLOCK, '--time' => self::TIME]);
                // The clean start: no "Refresh intelligence" and no evaluation after this point.
            }

            if ($populated && ! $startFromPopulated) {
                $this->populate();
            }

            if ($full) {
                app(DemoTimeline::class)->play(Company::query()->where('is_demo', true)->firstOrFail());
            }
        } finally {
            config(['queue.default' => $previousQueue]);
        }

        return $this->summary(Company::query()->where('is_demo', true)->firstOrFail(), false, $populated);
    }

    /**
     * Week 8 arrives (clock 28 Sep 09:00): the real import job and evaluation run, so the decline is detected by the
     * production engine (open high issue, urgent notifications). A draft meeting is then created for the Manager and
     * linked to the issue. No AI runs and no task, draft or outcome is written: those come from the real extraction
     * and verification once an AI provider is configured.
     */
    private function populate(): void
    {
        $this->run('demo:import', ['fixture' => 'week8-decline']);

        $company = Company::query()->where('is_demo', true)->firstOrFail();
        $departmentIds = $company->departments()->pluck('id');
        $issue = Issue::query()->whereIn('department_id', $departmentIds)->whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->first();
        $evaluation = RuleEvaluation::query()->whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->orderByDesc('id')->first();

        if ($issue === null || $issue->severity->value !== 'high' || $evaluation === null || $evaluation->result->value !== 'breached') {
            throw new DemoSeedException('Week 8 did not produce the documented high severity decline issue.');
        }

        $manager = User::query()->where('company_id', $company->id)->where('role', 'manager')->orderBy('id')->firstOrFail();

        $meeting = Meeting::query()->create([
            'department_id' => $manager->department_id,
            'created_by' => $manager->id,
            'title' => self::MEETING_TITLE,
            'meeting_date' => self::START_CLOCK,
            'status' => MeetingStatus::Draft,
        ]);
        $meeting->issues()->sync([$issue->id]);
        MeetingCreated::dispatch($meeting, $manager->id);
    }

    private function alreadyFull(Company $company): bool
    {
        $departmentIds = $company->departments()->pluck('id');

        return Meeting::query()->whereIn('department_id', $departmentIds)->where('title', DemoTimeline::SECOND_MEETING_TITLE)->exists()
            && DB::table('tasks')->whereIn('department_id', $departmentIds)->exists()
            && DB::table('outcomes')->exists();
    }

    private function alreadyPopulated(Company $company): bool
    {
        $departmentIds = $company->departments()->pluck('id');

        return User::query()->where('company_id', $company->id)->count() === 6
            && Import::query()->whereIn('department_id', $departmentIds)->where('status', 'completed')->count() === 2
            && Issue::query()->whereIn('department_id', $departmentIds)->whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->count() === 1
            && Meeting::query()->whereIn('department_id', $departmentIds)->where('title', self::MEETING_TITLE)->count() === 1
            && ! DB::table('tasks')->whereIn('department_id', $departmentIds)->exists()
            && ! DB::table('briefs')->where('company_id', $company->id)->exists();
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

    private function summary(Company $company, bool $already, bool $populated = false): SeedSummary
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
            $populated,
        );
    }
}
