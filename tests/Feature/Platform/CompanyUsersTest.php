<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    PlatformAdmin::create(['name' => 'Pat Platform', 'email' => 'pat@platform.test', 'password' => 'platform-pass-123']);
    $this->f = meetingRoster();
    $this->base = "/platform/companies/{$this->f->company->id}";
});

function usersAdmin()
{
    test()->post('/platform/login', ['email' => 'pat@platform.test', 'password' => 'platform-pass-123']);

    return test();
}

function userPayload(array $overrides = [], bool $withPassword = true): array
{
    $payload = array_replace([
        'name' => 'Nora Staff', 'email' => 'Nora@Meridian.test', 'role' => 'staff', 'job_title' => 'Sales Executive',
        'department_id' => test()->f->sales->id, 'manager_id' => test()->f->manager->id, 'password' => 'long-enough-pass-1',
    ], $overrides);

    // An edit never carries a password (a reset has its own action).
    return $withPassword ? $payload : array_diff_key($payload, ['password' => true]);
}

test('the company page lists its users with their place in the company and no secrets', function () {
    usersAdmin()->get($this->base)->assertOk()->assertInertia(fn ($p) => $p
        ->where('users.0.role', 'owner')->where('users.1.role', 'manager')->has('users', 6)
        ->has('departments', 1)->where('managers.0.name', 'Nurul Aisyah')
        ->missing('users.0.password')->missing('users.0.remember_token'));
    expect(test()->get($this->base)->getContent())->not->toContain('$2y$');
});

test('a user of every role can be added with the right place in the company, and can sign in', function () {
    usersAdmin();
    test()->post("{$this->base}/users", userPayload())->assertRedirect($this->base);
    test()->post("{$this->base}/users", userPayload(['name' => 'Mia Manager', 'email' => 'mia@meridian.test', 'role' => 'manager', 'manager_id' => null]))->assertRedirect();
    test()->post("{$this->base}/users", userPayload(['name' => 'Olive Owner', 'email' => 'olive@meridian.test', 'role' => 'owner', 'department_id' => null, 'manager_id' => null]))->assertRedirect();

    $staff = User::where('email', 'nora@meridian.test')->sole();
    $manager = User::where('email', 'mia@meridian.test')->sole();
    $owner = User::where('email', 'olive@meridian.test')->sole();
    expect($staff->role->value)->toBe('staff')->and($staff->company_id)->toBe($this->f->company->id)->and($staff->manager_id)->toBe($this->f->manager->id)->and($staff->is_active)->toBeTrue()
        ->and($manager->manager_id)->toBe($this->f->owner->id)->and($manager->department_id)->toBe($this->f->sales->id)
        ->and($owner->department_id)->toBeNull()->and($owner->manager_id)->toBeNull();
    expect(json_encode(PlatformAuditLog::where('action', 'user.created')->pluck('properties')))->not->toContain('long-enough');

    test()->post('/platform/logout');
    test()->post('/login', ['email' => 'nora@meridian.test', 'password' => 'long-enough-pass-1'])->assertRedirect('/my-tasks');
});

test('invalid users are rejected and nothing is created', function (array $override, string $field) {
    $before = User::count();

    usersAdmin()->post("{$this->base}/users", userPayload($override))->assertSessionHasErrors($field);

    expect(User::count())->toBe($before);
})->with([
    'used email' => [['email' => 'priya@m.test'], 'email'],
    'short password' => [['password' => 'short'], 'password'],
    'staff without a manager' => [['manager_id' => null], 'manager_id'],
    'a manager of another department' => [['manager_id' => 999999], 'manager_id'],
    'a department of another company' => [['department_id' => 999999], 'department_id'],
]);

test('a user can be edited, role changes end their sessions, and the audit lists field names only', function () {
    usersAdmin();
    DB::table('sessions')->insert(['id' => 's-priya', 'user_id' => $this->f->priya->id, 'payload' => '', 'last_activity' => time()]);

    test()->put("{$this->base}/users/{$this->f->priya->id}", userPayload(['name' => 'Priya N.', 'email' => 'priya@m.test', 'role' => 'manager', 'manager_id' => null, 'job_title' => 'Team lead'], false))->assertRedirect($this->base);

    $priya = $this->f->priya->fresh();
    expect($priya->name)->toBe('Priya N.')->and($priya->role->value)->toBe('manager')->and($priya->job_title)->toBe('Team lead')
        ->and(DB::table('sessions')->where('user_id', $priya->id)->count())->toBe(0);
    $log = PlatformAuditLog::where('action', 'user.updated')->sole();
    expect($log->properties['fields'])->toContain('role')->and(json_encode($log->properties))->not->toContain('Priya N.');
});

test('a user can be deactivated and reactivated; a deactivated user cannot sign in', function () {
    usersAdmin();
    test()->post("{$this->base}/users/{$this->f->priya->id}/deactivate")->assertRedirect();
    expect($this->f->priya->fresh()->is_active)->toBeFalse();

    test()->post('/platform/logout');
    test()->post('/login', ['email' => 'priya@m.test', 'password' => 'password'])->assertSessionHasErrors('email');
    usersAdmin();

    test()->post("{$this->base}/users/{$this->f->priya->id}/deactivate"); // repeating changes nothing
    expect(PlatformAuditLog::where('action', 'user.deactivated')->count())->toBe(1);
    test()->post("{$this->base}/users/{$this->f->priya->id}/reactivate")->assertRedirect();
    expect($this->f->priya->fresh()->is_active)->toBeTrue();
});

test('a company always keeps one active Owner', function () {
    usersAdmin();

    test()->post("{$this->base}/users/{$this->f->owner->id}/deactivate")->assertSessionHasErrors('user');
    test()->put("{$this->base}/users/{$this->f->owner->id}", userPayload(['email' => 'weihan@m.test', 'role' => 'manager', 'manager_id' => null], false))->assertSessionHasErrors('user');
    expect($this->f->owner->fresh()->is_active)->toBeTrue()->and($this->f->owner->fresh()->role->value)->toBe('owner');

    // With a second Owner the first can step down.
    test()->post("{$this->base}/users", userPayload(['name' => 'Olive Owner', 'email' => 'olive@meridian.test', 'role' => 'owner', 'department_id' => null, 'manager_id' => null]));
    test()->post("{$this->base}/users/{$this->f->owner->id}/deactivate")->assertRedirect();
    expect($this->f->owner->fresh()->is_active)->toBeFalse();
});

test('a password can be reset for any user, ends their sessions and is never audited', function () {
    usersAdmin();
    DB::table('sessions')->insert(['id' => 's-priya', 'user_id' => $this->f->priya->id, 'payload' => '', 'last_activity' => time()]);

    test()->put("{$this->base}/users/{$this->f->priya->id}/password", ['password' => 'short'])->assertSessionHasErrors('password');
    test()->put("{$this->base}/users/{$this->f->priya->id}/password", ['password' => 'a-brand-new-password'])->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $this->f->priya->id)->count())->toBe(0)
        ->and(json_encode(PlatformAuditLog::where('action', 'user.password_reset')->pluck('properties')))->not->toContain('brand-new');
    test()->post('/platform/logout');
    test()->post('/login', ['email' => 'priya@m.test', 'password' => 'a-brand-new-password'])->assertRedirect();
});

test('a user of another company is a 404 and company users cannot use these routes', function () {
    $stranger = User::factory()->staff(Department::factory()->create(['company_id' => Company::factory()->create()->id, 'slug' => 'sales']))->create();
    usersAdmin();

    test()->put("{$this->base}/users/{$stranger->id}/password", ['password' => 'a-brand-new-password'])->assertNotFound();
    test()->post("{$this->base}/users/{$stranger->id}/deactivate")->assertNotFound();
    test()->post('/platform/logout');

    test()->actingAs($this->f->owner)->post("{$this->base}/users", userPayload())->assertRedirect('/platform/login');
    expect(User::where('email', 'nora@meridian.test')->exists())->toBeFalse();
});
