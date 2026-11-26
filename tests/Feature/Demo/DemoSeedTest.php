<?php

use App\Models\BusinessRule;
use App\Models\Company;
use App\Models\Import;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\RuleEvaluation;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

test('the populated seed imports week 8 through the real pipeline: open high issue, urgent notifications and a draft meeting, no AI data', function () {
    $this->artisan('demo:seed', ['--populated' => true])->expectsOutputToContain('populated')->assertExitCode(0);

    $decline = Issue::whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->sole();
    $evaluation = RuleEvaluation::whereHas('rule', fn ($q) => $q->where('key', 'weekly_revenue_decline'))->orderByDesc('id')->first();
    $meeting = Meeting::sole();

    expect($decline->severity->value)->toBe('high')->and($decline->status->value)->toBe('open')
        ->and($evaluation->result->value)->toBe('breached')->and(round((float) $evaluation->difference_pct, 2))->toBe(-20.00)
        ->and(Import::where('status', 'completed')->count())->toBe(2)->and(SalesRecord::where('is_active', true)->count())->toBe(144)
        ->and(DB::table('notifications')->count())->toBe(2) // the Owner and the Manager of Sales
        ->and($meeting->title)->toBe('Sales recovery huddle')->and($meeting->status->value)->toBe('draft')->and($meeting->issues()->pluck('issues.id')->all())->toBe([$decline->id])
        ->and(DB::table('tasks')->count())->toBe(0)->and(DB::table('task_drafts')->count())->toBe(0)->and(DB::table('briefs')->count())->toBe(0)->and(DB::table('outcomes')->count())->toBe(0);
});

test('the populated seed is idempotent, upgrades a clean seed, and the clean seed refuses a populated demo', function () {
    $this->artisan('demo:seed')->assertExitCode(0); // clean start first
    $this->artisan('demo:seed', ['--populated' => true])->expectsOutputToContain('populated')->assertExitCode(0);
    $counts = [Issue::count(), Import::count(), Meeting::count(), SalesRecord::count()];

    $this->artisan('demo:seed', ['--populated' => true])->expectsOutputToContain('nothing changed')->assertExitCode(0);
    expect([Issue::count(), Import::count(), Meeting::count(), SalesRecord::count()])->toBe($counts);

    $this->artisan('demo:seed')->expectsOutputToContain('demo:reset')->assertExitCode(1);
});
