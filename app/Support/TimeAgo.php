<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * A human-readable "how long ago" for a moment ("just now", "5 minutes ago", "1 hour ago", "2 days ago"), measured
 * against the application's business clock (so it follows the demo clock in demo mode, never the browser's clock).
 */
final class TimeAgo
{
    public function __construct(private readonly BusinessClock $clock) {}

    public function label(?CarbonInterface $time): ?string
    {
        if ($time === null) {
            return null;
        }

        $now = $this->clock->nowUtc();

        // Under a minute either way reads as "just now" (also covers tiny clock differences).
        if (abs($now->diffInSeconds($time, false)) < 60) {
            return 'just now';
        }

        return $time->diffForHumans($now, CarbonInterface::DIFF_RELATIVE_TO_NOW);
    }
}
