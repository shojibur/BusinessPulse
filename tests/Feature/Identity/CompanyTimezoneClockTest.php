<?php

use App\Models\Company;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['demo.mode' => false, 'business.timezone' => 'Asia/Kuala_Lumpur']);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-10 00:30:00', 'UTC'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

test('with a company the company timezone is used, and an explicit argument still wins', function () {
    Company::factory()->create(['timezone' => 'Asia/Tokyo']);
    $clock = app(BusinessClock::class);

    expect($clock->now()->format('Y-m-d H:i'))->toBe('2026-03-10 09:30')
        ->and($clock->today()->format('Y-m-d H:i'))->toBe('2026-03-10 00:00')
        ->and($clock->now('UTC')->format('H:i'))->toBe('00:30');
});

test('the demo clock file is read in the company timezone', function () {
    Company::factory()->create(['timezone' => 'Asia/Tokyo']);
    config(['demo.mode' => true]);
    Storage::disk('local')->put('demo/fixed-datetime', '2025-09-22 09:00');

    $clock = app(BusinessClock::class);

    expect($clock->now()->format('Y-m-d H:i'))->toBe('2025-09-22 09:00')
        ->and($clock->now()->timezoneName)->toBe('Asia/Tokyo');
});
