<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Support\Facades\Hash;

const DEMO_TEST_PASSWORD = 'demo-test-password-123';

function seedDemo(): void
{
    test()->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->assertSuccessful();
}

function assertNothingWritten(): void
{
    expect(Company::count())->toBe(0)->and(Department::count())->toBe(0)->and(User::count())->toBe(0);
}

beforeEach(fn () => config(['demo.mode' => true, 'demo.password' => DEMO_TEST_PASSWORD]));

// Property 4: idempotence and guards.

test('running the seeder twice produces exactly one company, one Sales department and six users', function () {
    seedDemo();
    $first = [Company::first()->id, Department::first()->id, User::orderBy('id')->pluck('id')->all()];

    seedDemo();

    expect(Company::count())->toBe(1)
        ->and(Department::count())->toBe(1)
        ->and(User::count())->toBe(6)
        ->and([Company::first()->id, Department::first()->id, User::orderBy('id')->pluck('id')->all()])->toBe($first);
});

test('the seeded company, department and users match the demonstration scenario', function () {
    seedDemo();

    $company = Company::first();
    expect($company->name)->toBe('Meridian Home Supplies Sdn Bhd')
        ->and($company->timezone)->toBe('Asia/Kuala_Lumpur')
        ->and($company->currency)->toBe('MYR')
        ->and($company->trading_days)->toBe([1, 2, 3, 4, 5, 6])
        ->and($company->reporting_cutoff_time)->toBe('12:00')
        ->and($company->reporting_cutoff_lag_days)->toBe(1)
        ->and($company->is_demo)->toBeTrue();

    $sales = Department::first();
    expect($sales->slug)->toBe('sales')
        ->and($sales->name)->toBe('Sales')
        ->and($sales->settings['expected_branches'])->toBe(['KL', 'PG', 'JB']);

    $owner = User::where('email', 'owner@meridian.demo')->first();
    $manager = User::where('email', 'aisyah@meridian.demo')->first();
    expect($owner->name)->toBe('Lim Wei Han')->and($owner->role)->toBe(Role::Owner)
        ->and($owner->department_id)->toBeNull()->and($owner->manager_id)->toBeNull()
        ->and($manager->name)->toBe('Nurul Aisyah')->and($manager->role)->toBe(Role::Manager)
        ->and($manager->manager_id)->toBe($owner->id)->and($manager->department_id)->toBe($sales->id);

    $staff = [
        'daniel.tan@meridian.demo' => ['Daniel Tan', 'KL'],
        'daniel.lee@meridian.demo' => ['Daniel Lee', 'KL'],
        'priya@meridian.demo' => ['Priya Nair', 'PG'],
        'farid@meridian.demo' => ['Farid Ismail', 'JB'],
    ];

    foreach ($staff as $email => [$name, $branch]) {
        $user = User::where('email', $email)->first();
        expect($user->name)->toBe($name)->and($user->role)->toBe(Role::Staff)
            ->and($user->manager_id)->toBe($manager->id)->and($user->department_id)->toBe($sales->id)
            ->and($user->job_title)->toContain("({$branch})");
    }

    // Two Daniels exist on purpose (ambiguous assignee demonstration).
    expect(User::where('name', 'like', 'Daniel %')->count())->toBe(2);
});

test('every demonstration user can sign in with the configured password, which is stored hashed', function () {
    seedDemo();

    foreach (User::all() as $user) {
        expect($user->email)->toEndWith('@meridian.demo')
            ->and($user->password)->not->toBe(DEMO_TEST_PASSWORD)
            ->and(Hash::check(DEMO_TEST_PASSWORD, $user->password))->toBeTrue();
    }
});

test('the seeder keeps other department settings when it runs again', function () {
    seedDemo();
    $sales = Department::first();
    $sales->update(['settings' => [...$sales->settings, 'intelligence' => ['last_evaluated_at' => '2026-09-21']]]);

    seedDemo();

    expect(Department::first()->settings)->toHaveKey('intelligence')->and(Department::first()->settings['expected_branches'])->toBe(['KL', 'PG', 'JB']);
});

test('the seeder refuses without DEMO_MODE=true and writes nothing', function (mixed $mode) {
    config(['demo.mode' => $mode]);

    $this->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->expectsOutputToContain('DEMO_MODE');

    assertNothingWritten();
})->with([false, null]);

test('the seeder refuses a missing or short DEMO_PASSWORD and writes nothing', function (?string $password) {
    config(['demo.password' => $password]);

    $this->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->expectsOutputToContain('DEMO_PASSWORD');

    assertNothingWritten();
})->with([null, '', 'seven77']);

test('the seeder refuses to run next to a real company and writes nothing more', function () {
    Company::factory()->create(['name' => 'Real Client Sdn Bhd', 'is_demo' => false]);

    $this->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->expectsOutputToContain('non-demo company');

    expect(Company::count())->toBe(1)->and(Department::count())->toBe(0)->and(User::count())->toBe(0);
});

test('the seeder never prints the password', function () {
    $this->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->doesntExpectOutputToContain(DEMO_TEST_PASSWORD)->assertSuccessful();

    config(['demo.password' => 'short']);
    $this->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->doesntExpectOutputToContain('short-11')->assertSuccessful();
});

test('DEMO_PASSWORD is empty in the environment template', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain("\nDEMO_PASSWORD=\n")->and(file_get_contents(base_path('.env.example')))->toContain("\nDEMO_MODE=false\n");
});
