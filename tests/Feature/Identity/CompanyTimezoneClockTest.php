<?php

use App\Models\Company;
use App\Services\CurrentCompany;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['demo.mode' => false, 'business.timezone' => 'Asia/Kuala_Lumpur']);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-10 00:30:00', 'UTC'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

test('without a company the configured business timezone is used', function () {
    expect(app(BusinessClock::class)->now()->format('Y-m-d H:i'))->toBe('2026-03-10 08:30');
});

test('with a company the company timezone is used, and an explicit argument still wins', function () {
    Company::factory()->create(['timezone' => 'Asia/Tokyo']);
    $clock = app(BusinessClock::class);

    expect($clock->now()->format('Y-m-d H:i'))->toBe('2026-03-10 09:30')
        ->and($clock->today()->format('Y-m-d H:i'))->toBe('2026-03-10 00:00')
        ->and($clock->now('UTC')->format('H:i'))->toBe('00:30');
});

test('a changed company timezone applies on the next resolution without a restart', function () {
    $company = Company::factory()->create(['timezone' => 'Asia/Tokyo']);
    expect(app(BusinessClock::class)->today()->format('Y-m-d'))->toBe('2026-03-10');

    $company->update(['timezone' => 'America/Los_Angeles']);
    app(CurrentCompany::class)->forget();

    expect(app(BusinessClock::class)->today()->format('Y-m-d'))->toBe('2026-03-09');
});

test('the demo clock file is read in the company timezone', function () {
    Company::factory()->create(['timezone' => 'Asia/Tokyo']);
    config(['demo.mode' => true]);
    Storage::disk('local')->put('demo/fixed-datetime', '2025-09-22 09:00');

    $clock = app(BusinessClock::class);

    expect($clock->now()->format('Y-m-d H:i'))->toBe('2025-09-22 09:00')
        ->and($clock->now()->timezoneName)->toBe('Asia/Tokyo');
});

test('CurrentCompany resolves the single company once per request', function () {
    expect(app(CurrentCompany::class)->resolve())->toBeNull();

    $company = Company::factory()->create();
    app(CurrentCompany::class)->forget();

    expect(app(CurrentCompany::class)->resolve()->id)->toBe($company->id)
        ->and(app(CurrentCompany::class))->toBe(app(CurrentCompany::class));
});
