<?php

namespace App\Services\Demo;

use Illuminate\Support\Facades\Cache;

/**
 * Marks "a demo reset is running" in the database cache store (visible to queue workers). Expires after 15 minutes if the
 * process dies. The matching Reset_Lock is `Cache::lock('demo-reset', 600)`.
 */
final class DemoResetFlag
{
    public const KEY = 'demo-reset-active';

    public const LOCK = 'demo-reset';

    public function set(): void
    {
        Cache::put(self::KEY, true, now()->addMinutes(15));
    }

    public function clear(): void
    {
        Cache::forget(self::KEY);
    }

    public function isActive(): bool
    {
        return (bool) Cache::get(self::KEY, false);
    }
}
