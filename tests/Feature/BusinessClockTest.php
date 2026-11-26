<?php

use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-10 15:30:00', 'Asia/Kuala_Lumpur'));
    config(['business.timezone' => 'Asia/Kuala_Lumpur']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function writeClock(string $content): void
{
    Storage::disk('local')->put(config('demo.clock_file'), $content);
}

test('demo mode off ignores the demo clock file', function () {
    config(['demo.mode' => false]);
    writeClock('2024-01-01 09:00');

    $clock = app(BusinessClock::class);

    expect($clock->today()->format('Y-m-d'))->toBe('2026-03-10')
        ->and($clock->now()->format('Y-m-d H:i'))->toBe('2026-03-10 15:30');
});

test('demo mode on without a file returns real time', function () {
    config(['demo.mode' => true]);

    expect(app(BusinessClock::class)->now()->format('Y-m-d H:i'))->toBe('2026-03-10 15:30');
});

test('demo mode on honors the file', function (string $date, string $time) {
    config(['demo.mode' => true]);
    writeClock("{$date} {$time}");

    $clock = app(BusinessClock::class);

    expect($clock->today()->format('Y-m-d'))->toBe($date)
        ->and($clock->today()->format('H:i'))->toBe('00:00')
        ->and($clock->now()->format('Y-m-d H:i'))->toBe("{$date} {$time}")
        ->and($clock->now()->timezoneName)->toBe('Asia/Kuala_Lumpur');
})->with([
    ['2024-01-01', '09:00'],
    ['2024-02-29', '23:59'],
    ['2025-12-31', '00:00'],
    ['2025-09-22', '13:45'],
]);

test('a timezone argument overrides the default and an invalid one throws', function () {
    config(['demo.mode' => false]);
    $clock = app(BusinessClock::class);

    expect($clock->now('UTC')->format('Y-m-d H:i'))->toBe('2026-03-10 07:30');

    $clock->now('Mars/Olympus');
})->throws(InvalidArgumentException::class, 'Mars/Olympus');

test('demo:set-date refuses without DEMO_MODE and leaves the file unchanged', function () {
    config(['demo.mode' => false]);

    $this->artisan('demo:set-date', ['date' => '2025-09-22'])
        ->expectsOutputToContain('DEMO_MODE')
        ->assertFailed();

    expect(Storage::disk('local')->exists('demo/fixed-datetime'))->toBeFalse();
});
