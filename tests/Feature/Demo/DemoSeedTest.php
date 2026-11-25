<?php

use App\Models\ActivityLog;
use App\Models\BusinessRule;
use App\Models\Company;
use App\Models\Import;
use App\Models\Issue;
use App\Models\RuleEvaluation;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\Demo\DemoSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['demo.mode' => true, 'demo.password' => 'demo-test-password-123']);
});

test('seed creates the demo company, six users, the rule override and the evaluated week 7 history without any issue', function () {
    $this->artisan('demo:seed')->assertExitCode(0);

    expect(Company::count())->toBe(1)->and(Company::first()->is_demo)->toBeTrue()->and(User::count())->toBe(6)
        ->and(BusinessRule::where('key', 'weekly_revenue_decline')->first()->params['high_drop_pct'])->toBe('15.00')
        ->and(SalesRecord::where('is_active', true)->count())->toBe(126)->and(Issue::count())->toBe(0);

    $decline = RuleEvaluation::whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->orderByDesc('id')->first();
    $target = RuleEvaluation::whereHas('rule', fn ($q) => $q->where('key', 'sales_target_shortfall'))->orderByDesc('id')->first();
    expect($decline->result->value)->toBe('not_breached')->and(round((float) $decline->difference_pct, 2))->toBe(0.65)
        ->and($target->result->value)->toBe('not_breached')->and(round((float) $target->difference_pct, 2))->toBe(99.36);

    // The demo starts on 28 Sep 09:00 with week 8 overdue and NOT evaluated.
    expect(trim(Storage::disk('local')->get('demo/fixed-datetime')))->toBe('2026-09-28 09:00')
        ->and(RuleEvaluation::whereDate('period_start', '2026-09-21')->count())->toBe(0);
});

test('the seed history clock equals the clock of the history fixture in expected.json', function () {
    $expected = json_decode(file_get_contents(base_path('database/demo/expected.json')), true);

    expect($expected['fixtures']['history'])->toHaveKey('evaluations')
        ->and(DemoSeeder::HISTORY_CLOCK)->toBe('2026-09-21')
        ->and(DemoSeeder::START_CLOCK)->toBe('2026-09-28');
});

test('seeding twice changes nothing', function () {
    $this->artisan('demo:seed')->assertExitCode(0);
    $counts = [User::count(), SalesRecord::count(), RuleEvaluation::count(), Import::count()];

    $this->artisan('demo:seed')->expectsOutputToContain('nothing changed')->assertExitCode(0);

    expect([User::count(), SalesRecord::count(), RuleEvaluation::count(), Import::count()])->toBe($counts);
});

test('extra data stops the seed and points to demo:reset', function () {
    $this->artisan('demo:seed')->assertExitCode(0);
    importFixture(User::where('role', 'owner')->first(), 'week8-decline', '2026-09-28 09:00');

    $this->artisan('demo:seed')->expectsOutputToContain('demo:reset')->assertExitCode(1);
});

test('a missing or short password stops the seed before any user exists', function (?string $password) {
    config(['demo.password' => $password]);

    $this->artisan('demo:seed')->expectsOutputToContain('DEMO_PASSWORD')->assertExitCode(1);
    expect(User::count())->toBe(0);
})->with([[null], ['short']]);

test('seeded records come only from the real import path', function () {
    $this->artisan('demo:seed')->assertExitCode(0);

    $import = Import::first();
    expect($import->status->value)->toBe('completed')->and(SalesRecord::where('import_id', '!=', $import->id)->count())->toBe(0)
        ->and(ActivityLog::where('action', 'import.completed')->count())->toBe(1);
});
