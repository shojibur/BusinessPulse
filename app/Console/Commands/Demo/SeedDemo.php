<?php

namespace App\Console\Commands\Demo;

use App\Services\Demo\DemoGuard;
use App\Services\Demo\DemoSeeder;
use App\Services\Demo\DemoSeedException;
use App\Services\Demo\GuardContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('demo:seed')]
#[Description('Seed the Meridian demonstration start state (company, six users, rules, week 7 history) through the real import path. Requires DEMO_MODE=true; idempotent; refuses when a non-demo company exists.')]
class SeedDemo extends Command
{
    public function handle(DemoGuard $guard, DemoSeeder $seeder): int
    {
        $result = $guard->check(new GuardContext('seed'));

        if (! $result->ok()) {
            foreach ($result->failures as $failure) {
                $this->components->error($failure);
            }

            return 2;
        }

        try {
            $summary = $seeder->seed();
        } catch (DemoSeedException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('The demo seed failed ('.class_basename($e).'). Run with -v for details.');
            $this->output->isVerbose() && $this->line($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($summary->alreadySeeded ? 'The demo is already seeded: nothing changed.' : 'Demo seeded.');
        $this->line("Users: {$summary->users}. Rule override: {$summary->ruleOverride}. History import: ".json_encode($summary->importCounters).". Clock: {$summary->clockDateTime}.");

        return self::SUCCESS;
    }
}
