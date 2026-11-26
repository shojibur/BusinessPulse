<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['demo.mode' => true, 'demo.password' => 'demo-test-password-123', 'demo.allow_production' => false]);
});

test('demo mode off refuses both commands, changes nothing and exits 2', function () {
    config(['demo.mode' => false]);

    $this->artisan('demo:seed')->expectsOutputToContain('DEMO_MODE=true')->assertExitCode(2);
    $this->artisan('demo:reset', ['--force' => true])->expectsOutputToContain('DEMO_MODE=true')->assertExitCode(2);
    expect(Company::count())->toBe(0)->and(User::count())->toBe(0);
});

test('a real company blocks both commands and every failed condition is named', function () {
    config(['demo.mode' => false]);
    $real = Company::factory()->create(['is_demo' => false, 'name' => 'Real Client Sdn Bhd']);

    $this->artisan('demo:reset', ['--force' => true])
        ->expectsOutputToContain('DEMO_MODE=true')
        ->expectsOutputToContain('not marked as demo')
        ->expectsOutputToContain('exactly one company')
        ->assertExitCode(2);

    config(['demo.mode' => true]);
    $this->artisan('demo:seed')->expectsOutputToContain('not marked as demo')->assertExitCode(2);
    expect(Company::count())->toBe(1)->and(Company::first()->id)->toBe($real->id);
});

test('reset needs exactly one demo company, the typed name or --force with a terminal', function () {
    // Zero companies.
    $this->artisan('demo:reset', ['--force' => true])->expectsOutputToContain('exactly one company')->assertExitCode(2);

    // Two demo companies.
    Company::factory()->count(2)->create(['is_demo' => true]);
    $this->artisan('demo:reset', ['--force' => true])->expectsOutputToContain('exactly one company')->assertExitCode(2);
    expect(Company::count())->toBe(2);
});

test('without --force a wrong typed name or no terminal refuses the reset', function () {
    Company::factory()->create(['is_demo' => true, 'name' => 'Meridian Demo Co']);

    $this->artisan('demo:reset')->expectsQuestion('Type the company name to confirm', 'wrong name')->expectsOutputToContain('does not match')->assertExitCode(2);
    $this->artisan('demo:reset', ['--no-interaction' => true])->expectsOutputToContain('--force')->assertExitCode(2);
    expect(Company::count())->toBe(1);
});
