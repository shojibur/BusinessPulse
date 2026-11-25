<?php

namespace App\Console\Commands;

use App\Support\BusinessClock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('demo:set-date {date : Business date as YYYY-MM-DD} {--time=09:00 : Business time as HH:MM (24-hour)}')]
#[Description('Set the demo clock used by the BusinessClock (requires DEMO_MODE=true)')]
class DemoSetDate extends Command
{
    public function handle(): int
    {
        if (! config('demo.mode')) {
            $this->components->error('demo:set-date requires DEMO_MODE=true. The demo clock file was not changed.');

            return self::FAILURE;
        }

        $date = (string) $this->argument('date');
        $time = (string) $this->option('time');

        if (! $this->isValidDate($date)) {
            $this->components->error("Invalid date [{$date}]. Use a real calendar date as YYYY-MM-DD.");

            return self::FAILURE;
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            $this->components->error("Invalid time [{$time}]. Use 24-hour HH:MM.");

            return self::FAILURE;
        }

        $written = Storage::disk('local')->put((string) config('demo.clock_file'), "{$date} {$time}");

        if ($written === false) {
            $this->components->error('Could not write the demo clock file. The demo clock was not changed.');

            return self::FAILURE;
        }

        $this->components->info("Demo clock set to {$date} {$time} ({$this->timezone()}).");

        return self::SUCCESS;
    }

    private function isValidDate(string $date): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private function timezone(): string
    {
        return app(BusinessClock::class)->now()->timezoneName;
    }
}
