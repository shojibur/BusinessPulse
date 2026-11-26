<?php

use App\Models\BusinessRule;
use App\Models\Company;
use App\Models\Department;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Database\Seeders\DemoIdentitySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

const PLATFORM_PASSWORD = 'platform-pass-123';

beforeEach(function () {
    $this->admin = PlatformAdmin::create(['name' => 'Pat Platform', 'email' => 'pat@platform.test', 'password' => PLATFORM_PASSWORD]);
});

function newCompanyPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Kedai Maju Sdn Bhd', 'timezone' => 'Asia/Kuala_Lumpur', 'trading_days' => [1, 2, 3, 4, 5, 6],
        'branches' => 'kl, pg ; jb', 'owner_name' => 'Aminah Yusof', 'owner_email' => 'Aminah@Kedai.test', 'owner_password' => 'owner-secret-12345',
    ], $overrides);
}

/** Signs the platform admin in through the real login form (a real session, no default-guard switch). */
function signedInAdmin()
{
    test()->post('/platform/login', ['email' => 'pat@platform.test', 'password' => PLATFORM_PASSWORD]);

    return test();
}

function createdCompany(): Company
{
    return Company::query()->where('name', 'Kedai Maju Sdn Bhd')->firstOrFail();
}

test('the panel is closed to guests and to company users, and platform admins cannot open company pages', function () {
    $f = meetingRoster();

    $this->get('/platform/companies')->assertRedirect('/platform/login');
    $this->post('/platform/companies', newCompanyPayload())->assertRedirect('/platform/login');
    $this->actingAs($f->owner)->get('/platform/companies')->assertRedirect('/platform/login');
    $this->actingAs($f->owner)->get('/platform/companies/'.$f->company->id)->assertRedirect('/platform/login');
    $this->actingAs($f->owner)->get('/platform/login')->assertOk(); // only the sign-in page, never the panel
});

test('a platform admin cannot open any company page', function () {
    foreach (['/today', '/imports', '/issues', '/meetings', '/tasks', '/briefs', '/outcomes', '/settings/company'] as $url) {
        signedInAdmin()->get($url)->assertRedirect('/login');
    }
});

test('a platform admin signs in and out; wrong, unknown and inactive logins look the same', function () {
    $this->post('/platform/login', ['email' => 'PAT@platform.test', 'password' => PLATFORM_PASSWORD])->assertRedirect('/platform/companies');
    $this->get('/platform/companies')->assertOk();
    expect(PlatformAuditLog::where('action', 'platform.signed_in')->count())->toBe(1);

    $this->post('/platform/logout')->assertRedirect('/platform/login');
    $this->get('/platform/companies')->assertRedirect('/platform/login');

    $this->admin->forceFill(['is_active' => false])->save();
    $messages = collect([
        ['pat@platform.test', PLATFORM_PASSWORD], ['pat@platform.test', 'wrong-password-1'], ['nobody@platform.test', PLATFORM_PASSWORD],
    ])->map(fn ($c) => $this->post('/platform/login', ['email' => $c[0], 'password' => $c[1]])->assertSessionHasErrors('email')->baseResponse->getSession()->get('errors')->first('email'));
    expect($messages->unique()->all())->toBe(['These credentials do not match our records.']);
});

test('platform login is throttled after five failures', function () {
    RateLimiter::clear('platform|pat@platform.test|127.0.0.1');
    foreach (range(1, 5) as $ignored) {
        $this->post('/platform/login', ['email' => 'pat@platform.test', 'password' => 'wrong-password-1'])->assertSessionHasErrors('email');
    }

    $this->post('/platform/login', ['email' => 'pat@platform.test', 'password' => PLATFORM_PASSWORD])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many login attempts');
    $this->assertGuest('platform');
});

test('creating a company sets up the company, Sales department, default rules and a working Owner', function () {
    signedInAdmin()->post('/platform/companies', newCompanyPayload())->assertRedirect();

    $company = createdCompany();
    $department = Department::where('company_id', $company->id)->sole();
    $owner = User::where('company_id', $company->id)->sole();

    expect($company->is_demo)->toBeFalse()->and($company->timezone)->toBe('Asia/Kuala_Lumpur')->and($company->trading_days)->toBe([1, 2, 3, 4, 5, 6])
        ->and($department->slug)->toBe('sales')->and($department->expectedBranches())->toBe(['KL', 'PG', 'JB'])
        ->and(BusinessRule::where('department_id', $department->id)->count())->toBe(3)
        ->and($owner->role->value)->toBe('owner')->and($owner->email)->toBe('aminah@kedai.test')->and($owner->is_active)->toBeTrue();

    $audit = PlatformAuditLog::where('action', 'company.created')->sole();
    expect($audit->company_id)->toBe($company->id)->and($audit->platform_admin_id)->toBe($this->admin->id)
        ->and(json_encode($audit->properties))->not->toContain('owner-secret');

    $this->post('/platform/logout');
    $this->post('/login', ['email' => 'aminah@kedai.test', 'password' => 'owner-secret-12345'])->assertRedirect('/today');
});

test('invalid company details are rejected and nothing is created', function (array $override, string $field) {
    User::factory()->create(['email' => 'taken@x.test', 'company_id' => Company::factory()->create()->id]);
    $before = [Company::count(), User::count(), Department::count()];

    signedInAdmin()->post('/platform/companies', newCompanyPayload($override))->assertSessionHasErrors($field);

    expect([Company::count(), User::count(), Department::count()])->toBe($before);
})->with([
    'short password' => [['owner_password' => 'short'], 'owner_password'],
    'used email' => [['owner_email' => 'taken@x.test'], 'owner_email'],
    'bad email' => [['owner_email' => 'nope'], 'owner_email'],
    'bad timezone' => [['timezone' => 'Mars/Base'], 'timezone'],
    'no trading day' => [['trading_days' => []], 'trading_days'],
    'bad weekday' => [['trading_days' => [9]], 'trading_days.0'],
    'bad branch code' => [['branches' => 'KL, <b>'], 'branches'],
    'missing name' => [['name' => ''], 'name'],
]);

test('a failure while setting up rolls everything back', function () {
    // The Owner is the last row written: failing here must undo the company, department and rules too.
    User::creating(fn () => throw new RuntimeException('boom'));
    $before = [Company::count(), User::count(), Department::count()];

    $this->withoutExceptionHandling();
    expect(fn () => signedInAdmin()->post('/platform/companies', newCompanyPayload()))->toThrow(RuntimeException::class);

    expect([Company::count(), User::count(), Department::count()])->toBe($before)->and(PlatformAuditLog::where('action', 'company.created')->count())->toBe(0);
});

test('suspending a company blocks its sign in and ends live sessions; reactivating restores access', function () {
    signedInAdmin()->post('/platform/companies', newCompanyPayload());
    $company = createdCompany();
    $owner = User::where('company_id', $company->id)->sole();
    $this->post('/platform/logout');

    $session = $this->actingAs($owner)->get('/today')->assertOk();

    signedInAdmin()->post("/platform/companies/{$company->id}/suspend")->assertRedirect();
    expect($company->refresh()->isSuspended())->toBeTrue();

    // A company user already signed in is signed out on the next request.
    $this->post('/platform/logout');
    $this->actingAs(User::findOrFail($owner->id))->get('/today')->assertRedirect('/login')->assertSessionHasErrors(['email' => "This company's account is suspended."]);

    // And cannot sign in again.
    $this->post('/login', ['email' => 'aminah@kedai.test', 'password' => 'owner-secret-12345'])->assertSessionHasErrors(['email' => "This company's account is suspended."]);
    $this->assertGuest();

    // Repeating the action changes nothing and writes no second audit row.
    signedInAdmin()->post("/platform/companies/{$company->id}/suspend");
    expect(PlatformAuditLog::where('action', 'company.suspended')->count())->toBe(1);

    $this->post("/platform/companies/{$company->id}/reactivate")->assertRedirect();
    $this->post('/platform/logout');
    $this->post('/login', ['email' => 'aminah@kedai.test', 'password' => 'owner-secret-12345'])->assertRedirect('/today');
    expect(PlatformAuditLog::where('action', 'company.reactivated')->count())->toBe(1);
});

test('the Owner password can be reset, ends the Owner sessions and is never logged', function () {
    signedInAdmin()->post('/platform/companies', newCompanyPayload());
    $company = createdCompany();
    $owner = User::where('company_id', $company->id)->sole();
    DB::table('sessions')->insert(['id' => 'sess-owner', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

    $this->put("/platform/companies/{$company->id}/owner-password", ['password' => 'short'])->assertSessionHasErrors('password');
    $this->put("/platform/companies/{$company->id}/owner-password", ['password' => 'a-brand-new-password'])->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0)
        ->and(json_encode(PlatformAuditLog::where('action', 'company.owner_password_reset')->sole()->properties))->not->toContain('brand-new');

    $this->post('/platform/logout');
    $this->post('/login', ['email' => 'aminah@kedai.test', 'password' => 'owner-secret-12345'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => 'aminah@kedai.test', 'password' => 'a-brand-new-password'])->assertRedirect('/today');
});

test('the panel shows company metadata only, never business data', function () {
    $f = reviewFixture(meetingRoster()); // an issue, a meeting and drafts exist for the roster company
    $roster = Company::query()->where('name', 'Meridian Test Sdn Bhd')->firstOrFail();

    $page = signedInAdmin()->get("/platform/companies/{$roster->id}")->assertOk();
    $page->assertInertia(fn ($p) => $p->component('platform/companies/Show')
        ->where('company.users_count', fn ($n) => $n >= 5)
        ->where('owner.email', 'weihan@m.test')
        ->missing('issues')->missing('meetings')->missing('imports')->missing('tasks'));

    $content = $page->getContent();
    expect($content)->not->toContain($f->issue->title)->not->toContain($f->meeting->title)->not->toContain('password')->not->toContain('$2y$');
});

test('two companies stay isolated and each user sees their own company', function () {
    $a = meetingRoster();
    $meeting = makeMeeting($a);
    $issue = reviewFixture($a)->issue;

    signedInAdmin()->post('/platform/companies', newCompanyPayload());
    $bOwner = User::where('company_id', createdCompany()->id)->sole();
    $this->post('/platform/logout');

    $this->actingAs($bOwner)->get("/meetings/{$meeting->id}")->assertNotFound();
    $this->actingAs($bOwner)->get("/issues/{$issue->id}")->assertNotFound();
    $this->actingAs($bOwner)->get('/meetings')->assertInertia(fn ($p) => $p->has('meetings', 0));
    $this->actingAs($bOwner)->get('/settings/company')->assertInertia(fn ($p) => $p->where('company.name', 'Kedai Maju Sdn Bhd'));
    // Same process, other user: the company is re-resolved, not remembered from the previous request.
    $this->actingAs($a->owner)->get('/settings/company')->assertInertia(fn ($p) => $p->where('company.name', 'Meridian Test Sdn Bhd'));
    $this->actingAs($a->owner)->get("/meetings/{$meeting->id}")->assertOk();
});

test('platform:create-admin creates an admin and refuses weak or duplicate input', function () {
    $this->artisan('platform:create-admin', ['--name' => 'Sam Admin', '--email' => 'sam@platform.test', '--password' => 'long-enough-pass-1'])->assertSuccessful();
    expect(PlatformAdmin::where('email', 'sam@platform.test')->exists())->toBeTrue();

    $this->artisan('platform:create-admin', ['--name' => 'Sam Two', '--email' => 'sam@platform.test', '--password' => 'long-enough-pass-1'])->assertFailed();
    $this->artisan('platform:create-admin', ['--name' => 'Weak', '--email' => 'weak@platform.test', '--password' => 'short'])->assertFailed();
    expect(PlatformAdmin::where('email', 'weak@platform.test')->exists())->toBeFalse();
});

test('the demo seeder creates the demo platform admin with the demo password', function () {
    config(['demo.mode' => true, 'demo.password' => 'demo-test-password-123']);

    $this->artisan('db:seed', ['--class' => DemoIdentitySeeder::class])->assertSuccessful();

    $admin = PlatformAdmin::where('email', 'admin@businesspulse.demo')->sole();
    expect(Hash::check('demo-test-password-123', $admin->password))->toBeTrue();
});
