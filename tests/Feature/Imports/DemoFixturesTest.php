<?php

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\Demo\FixtureGenerator;
use Database\Seeders\DemoIdentitySeeder;

// Property 7: fixtures are deterministic and consistent. Fictional data only.

function seedDemoIdentity(): void
{
    config(['demo.mode' => true, 'demo.password' => 'demo-test-password-123']);
    test()->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->assertSuccessful();
}

function weeklyTotal(string $monday, string $saturday): string
{
    $sum = '0';
    foreach (SalesRecord::where('is_active', true)->whereBetween('sales_date', [$monday, $saturday])->pluck('revenue') as $revenue) {
        $sum = bcadd($sum, (string) $revenue, 2);
    }

    return $sum;
}

test('demo:check-fixtures passes without touching the database', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->artisan('demo:check-fixtures')->expectsOutputToContain('All demo fixtures match')->assertExitCode(0);

    expect($queries)->toBe(0)->and(SalesRecord::count())->toBe(0);
});

test('the generator is deterministic and matches the committed files byte for byte', function () {
    $generator = new FixtureGenerator;

    expect($generator->files())->toBe((new FixtureGenerator)->files());

    foreach ($generator->files() as $name => $content) {
        expect(file_get_contents(base_path("database/demo/{$name}")))->toBe($content)
            ->and($content)->toStartWith("sales_date,branch_code,revenue,target_revenue,order_count\n")
            ->and($content)->not->toContain("\r")->not->toContain("\xEF\xBB\xBF")->toEndWith("\n");
    }
});

test('demo:import refuses without DEMO_MODE and for unknown fixtures or a missing demo company', function () {
    config(['demo.mode' => false]);
    $this->artisan('demo:import', ['fixture' => 'history'])->expectsOutputToContain('DEMO_MODE')->assertFailed();

    config(['demo.mode' => true]);
    $this->artisan('demo:import', ['fixture' => 'nope'])->expectsOutputToContain('Unknown fixture')->assertFailed();
    $this->artisan('demo:import', ['fixture' => 'history'])->expectsOutputToContain('DemoIdentitySeeder')->assertFailed();

    expect(Import::count())->toBe(0)->and(SalesRecord::count())->toBe(0);
});

test('demo:import loads the whole demo timeline through the real workflow with the documented totals', function () {
    seedDemoIdentity();

    setClock('2026-09-21 09:00');
    $this->artisan('demo:import', ['fixture' => 'history.csv'])->assertSuccessful();
    expect(SalesRecord::where('is_active', true)->count())->toBe(126)
        ->and(weeklyTotal('2026-08-03', '2026-08-08'))->toBe('150000.00')
        ->and(weeklyTotal('2026-08-10', '2026-08-15'))->toBe('152000.00')
        ->and(weeklyTotal('2026-08-17', '2026-08-22'))->toBe('149000.00')
        ->and(weeklyTotal('2026-08-24', '2026-08-29'))->toBe('153000.00')
        ->and(weeklyTotal('2026-08-31', '2026-09-05'))->toBe('151000.00')
        ->and(weeklyTotal('2026-09-07', '2026-09-12'))->toBe('154000.00')
        ->and(weeklyTotal('2026-09-14', '2026-09-19'))->toBe('155000.00');

    setClock('2026-09-28 09:00');
    $this->artisan('demo:import', ['fixture' => 'week8-decline'])->assertSuccessful();
    expect(weeklyTotal('2026-09-21', '2026-09-26'))->toBe('124000.00');

    $this->artisan('demo:import', ['fixture' => 'week8-correction'])->expectsOutputToContain('new 0, corrected 2, unchanged 16')->assertSuccessful();
    expect(weeklyTotal('2026-09-21', '2026-09-26'))->toBe('126000.00')
        ->and(SalesRecord::where('is_active', false)->count())->toBe(2);

    setClock('2026-10-05 09:00');
    $this->artisan('demo:import', ['fixture' => 'week9-interim'])->assertSuccessful();
    expect(weeklyTotal('2026-09-28', '2026-10-03'))->toBe('138000.00');

    setClock('2026-10-12 09:00');
    $this->artisan('demo:import', ['fixture' => 'week10-recovery'])->assertSuccessful();
    expect(weeklyTotal('2026-10-05', '2026-10-10'))->toBe('150000.00');

    $owner = User::where('email', 'owner@meridian.demo')->first();
    expect(Import::count())->toBe(5)
        ->and(Import::pluck('uploaded_by')->unique()->all())->toBe([$owner->id])
        ->and(Import::where('status', ImportStatus::Completed)->count())->toBe(5);
});

test('the errors fixture imports 17 valid rows, reports 3 errors and leaves the Saturday JB day missing', function () {
    seedDemoIdentity();
    setClock('2026-09-21 09:00');
    $this->artisan('demo:import', ['fixture' => 'history'])->assertSuccessful();

    setClock('2026-09-28 09:00');
    $this->artisan('demo:import', ['fixture' => 'week8-with-errors'])
        ->expectsOutputToContain('row 19 target_revenue: invalid_number')
        ->expectsOutputToContain('row 20 sales_date: invalid_date')
        ->expectsOutputToContain('row 21 revenue: negative_value')
        ->assertSuccessful();

    expect(Import::latest('id')->first()->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and(SalesRecord::where('branch_code', 'JB')->where('sales_date', '2026-09-26')->exists())->toBeFalse()
        ->and(weeklyTotal('2026-09-21', '2026-09-26'))->toBe('119231.00');
});
