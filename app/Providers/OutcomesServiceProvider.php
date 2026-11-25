<?php

namespace App\Providers;

use App\Listeners\Activity\RecordActivity;
use App\Services\Activity\ActivityMap;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * S08 wiring: the explicit event-to-listener registration of the activity recorder (one place to read), and the write
 * limiter. The outcome-verification trigger listener is discovered by its type-hint like the other S04 listeners.
 */
class OutcomesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (array_keys(ActivityMap::core()) as $eventClass) {
            Event::listen($eventClass, RecordActivity::class);
        }

        RateLimiter::for('s08-writes', fn (Request $request) => Limit::perMinute(20)->by('s08:'.$request->user()?->id));
    }
}
