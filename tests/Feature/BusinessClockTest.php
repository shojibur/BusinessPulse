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

test('demo:set-date round trips through the clock', function (string $date, string $time) {
    config(['demo.mode' => true]);

    $this->artisan('demo:set-date', ['date' => $date, '--time' => $time])
        ->expectsOutputToContain("{$date} {$time} (Asia/Kuala_Lumpur)")
        ->assertSuccessful();

    $clock = app(BusinessClock::class);

    expect($clock->today()->format('Y-m-d'))->toBe($date)
        ->and($clock->now()->format('H:i'))->toBe($time);
})->with([
    ['2024-01-01', '09:00'],
    ['2024-02-29', '18:05'],
    ['2025-12-31', '00:00'],
]);

test('demo:set-date defaults the time to 09:00', function () {
    config(['demo.mode' => true]);

    $this->artisan('demo:set-date', ['date' => '2025-09-22'])->assertSuccessful();

    expect(Storage::disk('local')->get('demo/fixed-datetime'))->toBe('2025-09-22 09:00');
});

test('an invalid demo clock file throws a descriptive exception naming the file', function (string $content) {
    config(['demo.mode' => true]);
    writeClock($content);

    app(BusinessClock::class)->now();
})->with(['garbage', '2025-02-30 09:00', '2025-01-01 25:00', '2025-01-01', ''])
    ->throws(InvalidArgumentException::class, 'demo/fixed-datetime');

test('a timezone argument overrides the default and an invalid one throws', function () {
    config(['demo.mode' => false]);
    $clock = app(BusinessClock::class);

    expect($clock->now('UTC')->format('Y-m-d H:i'))->toBe('2026-03-10 07:30');

    $clock->now('Mars/Olympus');
})->throws(InvalidArgumentException::class, 'Mars/Olympus');

test('BusinessClock resolves as a singleton from the container', function () {
    expect(app(BusinessClock::class))->toBe(app(BusinessClock::class));
});

test('demo:set-date refuses without DEMO_MODE and leaves the file unchanged', function () {
    config(['demo.mode' => false]);

    $this->artisan('demo:set-date', ['date' => '2025-09-22'])
        ->expectsOutputToContain('DEMO_MODE')
        ->assertFailed();

    expect(Storage::disk('local')->exists('demo/fixed-datetime'))->toBeFalse();
});

test('demo:set-date rejects invalid input and leaves the file unchanged', function (string $date, string $time, string $named) {
    config(['demo.mode' => true]);
    writeClock('2025-01-01 09:00');

    $this->artisan('demo:set-date', ['date' => $date, '--time' => $time])
        ->expectsOutputToContain($named)
        ->assertFailed();

    expect(Storage::disk('local')->get('demo/fixed-datetime'))->toBe('2025-01-01 09:00');
})->with([
    ['2025-02-30', '09:00', '2025-02-30'],
    ['not-a-date', '09:00', 'not-a-date'],
    ['2025-09-22', '24:00', '24:00'],
    ['2025-09-22', '9:5', '9:5'],
]);

test('demo:set-date reports failure when the clock file cannot be written', function () {
    config(['demo.mode' => true]);
    $disk = Mockery::mock();
    $disk->shouldReceive('put')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    $this->artisan('demo:set-date', ['date' => '2025-09-22'])
        ->expectsOutputToContain('Could not write the demo clock file')
        ->assertFailed();
});
