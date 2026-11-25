<?php

namespace App\Support;

use App\Services\CurrentCompany;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * The single source of the business date and time.
 *
 * With DEMO_MODE=true and a valid demo clock file, the clock returns the demo
 * date-time; otherwise it returns real time. Business-date decisions elsewhere
 * in the application must go through this class.
 */
class BusinessClock
{
    private const FILE_FORMAT = 'Y-m-d H:i';

    /**
     * The current date-time in the business timezone (or the given timezone).
     */
    public function now(?string $timezone = null): CarbonImmutable
    {
        $timezone = $this->resolveTimezone($timezone);
        $demo = $this->demoNow();

        return $demo !== null ? $demo->setTimezone($timezone) : CarbonImmutable::now($timezone);
    }

    /**
     * The current date (time truncated) in the business timezone (or the given timezone).
     */
    public function today(?string $timezone = null): CarbonImmutable
    {
        return $this->now($timezone)->startOfDay();
    }

    private function resolveTimezone(?string $timezone): string
    {
        // Explicit argument wins, then the company timezone, then the configured default (before a company exists).
        $timezone ??= app(CurrentCompany::class)->resolve()?->timezone ?? (string) config('business.timezone');

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("Invalid timezone identifier [{$timezone}].");
        }

        return $timezone;
    }

    /**
     * The demo date-time, interpreted in the business timezone, or null when
     * demo mode is off or the demo clock file does not exist.
     */
    private function demoNow(): ?CarbonImmutable
    {
        if (! config('demo.mode')) {
            return null;
        }

        $disk = Storage::disk('local');
        $file = (string) config('demo.clock_file');

        if (! $disk->exists($file)) {
            return null;
        }

        $content = trim((string) $disk->get($file));
        $businessTimezone = $this->resolveTimezone(null);
        $parsed = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $content) === 1
            ? CarbonImmutable::createFromFormat('!'.self::FILE_FORMAT, $content, $businessTimezone)
            : false;

        // Re-formatting rejects impossible values such as 2025-02-30 that PHP would roll over.
        if ($parsed === false || $parsed->format(self::FILE_FORMAT) !== $content) {
            throw new InvalidArgumentException(
                "Demo clock file [{$file}] must contain a valid 'YYYY-MM-DD HH:MM' date-time; found [{$content}]."
            );
        }

        return $parsed;
    }
}
