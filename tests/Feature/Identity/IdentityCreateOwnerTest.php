<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;

const OWNER_TEST_PASSWORD = 'a-long-owner-password';

function createOwner(array $overrides = []): PendingCommand
{
    return test()->artisan('identity:create-owner', [
        '--company' => 'Acme Trading Sdn Bhd',
        '--name' => 'Ada Owner',
        '--email' => 'Ada@Acme.Test',
        '--password' => OWNER_TEST_PASSWORD,
        ...$overrides,
    ]);
}

test('it creates the company, the Sales department and the Owner in one go', function () {
    createOwner()->doesntExpectOutputToContain(OWNER_TEST_PASSWORD)->assertSuccessful();

    $owner = User::first();
    expect(Company::count())->toBe(1)
        ->and(Department::count())->toBe(1)
        ->and(Department::first()->slug)->toBe('sales')
        ->and(Department::first()->name)->toBe('Sales')
        ->and($owner->email)->toBe('ada@acme.test')
        ->and($owner->role)->toBe(Role::Owner)
        ->and($owner->department_id)->toBeNull()
        ->and($owner->manager_id)->toBeNull()
        ->and($owner->is_active)->toBeTrue()
        ->and($owner->company_id)->toBe(Company::first()->id)
        ->and($owner->password)->not->toBe(OWNER_TEST_PASSWORD)
        ->and(Hash::check(OWNER_TEST_PASSWORD, $owner->password))->toBeTrue()
        ->and(Company::first()->is_demo)->toBeFalse();
});

test('a second run aborts and creates nothing', function () {
    createOwner()->assertSuccessful();

    createOwner(['--email' => 'second@acme.test'])->expectsOutputToContain('already exists')->assertFailed();

    expect(Company::count())->toBe(1)->and(User::count())->toBe(1)->and(Department::count())->toBe(1);
});

test('an invalid email or a short password aborts with no writes and never echoes the password', function (array $override, string $message) {
    createOwner($override)->expectsOutputToContain($message)->doesntExpectOutputToContain('short-pass')->assertFailed();

    expect(Company::count())->toBe(0)->and(Department::count())->toBe(0)->and(User::count())->toBe(0);
})->with([
    'invalid email' => [['--email' => 'not-an-email'], 'email'],
    'short password' => [['--password' => 'short-pass'], 'password'],
]);
