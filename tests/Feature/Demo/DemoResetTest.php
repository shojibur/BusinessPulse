<?php

use App\Models\ActivityLog;
use App\Models\BusinessRule;
use App\Models\Company;
use App\Models\Department;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\Outcome;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\Demo\DemoResetFlag;
use App\Services\Demo\DemoSeeder;
use App\Services\Demo\SeedSummary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['demo.mode' => true, 'demo.password' => 'demo-test-password-123']);
    $this->artisan('demo:seed')->assertExitCode(0);
});

function demoState(): array
{
    return [
        'users' => User::count(), 'rules' => BusinessRule::count(), 'active' => SalesRecord::where('is_active', true)->count(),
        'revenue' => (string) SalesRecord::where('is_active', true)->sum('revenue'), 'issues' => Issue::count(),
    ];
}

test('reset refuses while a job is queued, changes nothing and releases the lock and flag', function () {
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
    $before = demoState();

    $this->artisan('demo:reset', ['--force' => true])->expectsOutputToContain('1 row(s)')->assertExitCode(1);

    expect(demoState())->toBe($before)->and(app(DemoResetFlag::class)->isActive())->toBeFalse()->and(Cache::lock('demo-reset', 5)->get())->toBeTrue();
});

test('reset deletes every demo row and file without a foreign key error, then re-seeds to the seed state', function () {
    $seeded = demoState();
    $owner = User::where('role', 'owner')->first();
    importFixture($owner, 'week8-decline', '2026-09-28 09:00');
    // A recording and brief audio file of the demo company, plus an unrelated file that must survive.
    $meeting = Meeting::create(['department_id' => Department::first()->id, 'created_by' => $owner->id, 'title' => 'Huddle', 'meeting_date' => '2026-09-28',
        'recording_path' => 'recordings/1/x.mp3', 'recording_mime' => 'audio/mpeg']);
    Storage::disk('local')->put('recordings/1/x.mp3', 'audio');
    Storage::disk('local')->put('unrelated/keep.txt', 'keep');
    expect(Issue::count())->toBeGreaterThan(0)->and(Outcome::count() + ActivityLog::count())->toBeGreaterThan(0);

    $this->artisan('demo:reset', ['--force' => true])->expectsOutputToContain('Demo reset.')->assertExitCode(0);

    expect(demoState())->toBe($seeded)->and(Company::count())->toBe(1)->and(Meeting::count())->toBe(0)->and(Storage::disk('local')->exists('recordings/1/x.mp3'))->toBeFalse()->and(Storage::disk('local')->exists('unrelated/keep.txt'))->toBeTrue();
});

test('two consecutive resets reach the same state', function () {
    $this->artisan('demo:reset', ['--force' => true])->assertExitCode(0);
    $first = demoState();
    $this->artisan('demo:reset', ['--force' => true])->assertExitCode(0);

    expect(demoState())->toBe($first)->and($first['active'])->toBe(126)->and($first['users'])->toBe(6);
});

test('the lock and flag are released after a failure inside the reset', function () {
    app()->bind(DemoSeeder::class, fn () => new class extends DemoSeeder
    {
        public function seed(): SeedSummary
        {
            throw new RuntimeException('seed exploded');
        }
    });

    $this->artisan('demo:reset', ['--force' => true])->assertExitCode(1);

    expect(app(DemoResetFlag::class)->isActive())->toBeFalse()->and(Cache::lock('demo-reset', 5)->get())->toBeTrue();
});
