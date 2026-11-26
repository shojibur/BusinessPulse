<?php

namespace App\Console\Commands\Demo;

use App\Models\Company;
use App\Services\Demo\DemoGuard;
use App\Services\Demo\DemoResetter;
use App\Services\Demo\DemoSeedException;
use App\Services\Demo\GuardContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('demo:reset {--force : Skip the typed confirmation} {--populated : Re-seed the populated demo (week 8 imported) instead of the clean presenter start}')]
#[Description('DESTRUCTIVE: delete ALL data of the demonstration company and its files, then re-seed it. Requires DEMO_MODE=true and exactly one company (the demo company). Refuses while queued jobs exist. Never run against real data.')]
class ResetDemo extends Command
{
    public function handle(DemoGuard $guard, DemoResetter $resetter): int
    {
        $interactive = $this->input->isInteractive();
        $typed = null;
        $company = Company::query()->where('is_demo', true)->first();

        if (! $this->option('force') && $interactive && $company !== null && Company::query()->count() === 1) {
            $this->components->warn("This DELETES all data of the demo company '{$company->name}'.");
            $typed = (string) $this->ask('Type the company name to confirm');
        }

        $result = $guard->check(new GuardContext('reset', (bool) $this->option('force'), $interactive, $typed));

        if (! $result->ok()) {
            foreach ($result->failures as $failure) {
                $this->components->error($failure);
            }

            return 2;
        }

        try {
            $summary = $resetter->reset((bool) $this->option('populated'));
        } catch (DemoSeedException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('The demo reset failed ('.class_basename($e).'). Run with -v for details.');

            return self::FAILURE;
        }

        $this->components->info('Demo reset.');
        foreach ($summary->deleted as $group => $count) {
            $this->line("  deleted {$group}: {$count}");
        }
        $this->line("Files deleted: {$summary->filesDeleted}".($summary->undeletedFiles === [] ? '' : '. Could not delete: '.implode(', ', $summary->undeletedFiles)));
        $this->line("Seed: users {$summary->seed->users}, import ".json_encode($summary->seed->importCounters).", clock {$summary->seed->clockDateTime}.");
        $summary->failedJobs > 0 && $this->components->warn("{$summary->failedJobs} row(s) remain in failed_jobs (not touched).");

        return self::SUCCESS;
    }
}
