<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController as Auth;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

function login(string $email, string $password = UserFactory::PASSWORD)
{
    return test()->post(route('login.store'), ['email' => $email, 'password' => $password]);
}

test('an active user can sign in, the session id is regenerated and they land on Today', function () {
    $f = identity();
    $before = session()->getId();

    login($f->staff->email)->assertRedirect(route('today'));

    $this->assertAuthenticatedAs($f->staff);
    expect(session()->getId())->not->toBe($before);
});

test('email matching is case insensitive', function () {
    $f = identity();

    login('  STAFF@Fixture.Test ')->assertRedirect(route('today'));

    $this->assertAuthenticatedAs($f->staff);
});

test('unknown email, wrong password and an inactive user get the same generic message and no session', function (string $email, string $password) {
    identity();

    login($email, $password)->assertSessionHasErrors(['email' => Auth::FAILED_MESSAGE]);

    $this->assertGuest();
})->with([
    'unknown email' => ['nobody@fixture.test', UserFactory::PASSWORD],
    'wrong password' => ['staff@fixture.test', 'not-the-password'],
    'inactive user with the correct password' => ['inactive@fixture.test', UserFactory::PASSWORD],
]);

test('failed attempts are throttled after five per email and IP and the wait is shown', function () {
    $f = identity();

    foreach (range(1, 5) as $ignored) {
        login($f->staff->email, 'wrong-password')->assertSessionHasErrors(['email' => Auth::FAILED_MESSAGE]);
    }

    // The sixth attempt is rejected even with the correct password.
    login($f->staff->email)->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many login attempts')->toContain('seconds');
    $this->assertGuest();

    // A different email from the same IP has its own bucket.
    login($f->manager->email)->assertRedirect(route('today'));
});

test('a successful sign in clears the failed attempt counter', function () {
    $f = identity();
    $key = $f->staff->email.'|127.0.0.1';

    login($f->staff->email, 'wrong-password');
    login($f->staff->email, 'wrong-password');
    expect(RateLimiter::attempts($key))->toBe(2);

    login($f->staff->email)->assertRedirect(route('today'));

    expect(RateLimiter::attempts($key))->toBe(0);
});

test('failed and throttled attempts are logged without the password', function () {
    $f = identity();
    Log::spy();

    foreach (range(1, 6) as $ignored) {
        login($f->staff->email, 'super-secret-wrong-password');
    }

    Log::shouldHaveReceived('warning')->with('Login failed', Mockery::on(fn ($context) => array_keys($context) === ['ip']));
    Log::shouldHaveReceived('warning')->with('Login throttled', Mockery::on(fn ($context) => array_keys($context) === ['ip']));
});

test('signed-in users are redirected away from the login page', function () {
    $this->actingAs(identity()->staff)->get(route('login'))->assertRedirect(route('today'));
});

test('the login page renders for guests with the owner reset hint and no remember-me', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('auth.user', null));

    $source = file_get_contents(resource_path('js/pages/auth/Login.vue'));
    expect($source)->toContain('Forgot your password? Ask your Owner to reset it.')->not->toContain('remember');
});

test('logout is POST only, ends the session and redirects to the login page', function () {
    $f = identity();
    login($f->staff->email);

    $this->get('/logout')->assertNotFound();
    $this->assertAuthenticated();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('guests are redirected to the login page, or get 401 JSON, for every protected route', function () {
    $paths = ['/today', '/issues', '/imports', '/briefs', '/meetings', '/tasks', '/outcomes', '/settings', '/settings/company', '/settings/staff', '/settings/department'];

    foreach ($paths as $path) {
        $this->get($path)->assertRedirect(route('login'));
        $this->getJson($path)->assertUnauthorized();
    }

    $this->post(route('logout'))->assertRedirect(route('login'));
});

test('public registration and email based recovery routes do not exist', function (string $method, string $path) {
    $this->call($method, $path)->assertNotFound();
})->with([
    ['GET', '/register'],
    ['POST', '/register'],
    ['GET', '/forgot-password'],
    ['POST', '/forgot-password'],
    ['GET', '/reset-password/some-token'],
    ['POST', '/reset-password'],
    ['GET', '/email/verify'],
    ['GET', '/email/verify/1/hash'],
    ['POST', '/email/verification-notification'],
    ['GET', '/user/confirm-password'],
    ['GET', '/two-factor-challenge'],
    ['POST', '/user/two-factor-authentication'],
    ['GET', '/settings/profile'],
    ['GET', '/settings/security'],
]);

test('a deactivated user with an open session is signed out on the next request', function () {
    $f = identity();
    login($f->staff->email);
    $this->get('/today')->assertOk();

    User::whereKey($f->staff->id)->update(['is_active' => false]);
    app('auth')->forgetGuards(); // a new request loads the user from the database again

    $this->get('/today')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => EnsureUserIsActive::MESSAGE]);
    $this->assertGuest();
});

test('a deactivated session gets a 401 JSON response on a JSON request', function () {
    $f = identity();
    login($f->staff->email);
    User::whereKey($f->staff->id)->update(['is_active' => false]);
    app('auth')->forgetGuards();

    $this->getJson('/today')->assertUnauthorized()->assertJson(['message' => EnsureUserIsActive::MESSAGE]);
    $this->assertGuest();
});

test('a deactivated user is also stopped on department scoped URLs before any record is resolved', function () {
    $f = identity();
    login($f->manager->email);
    User::whereKey($f->manager->id)->update(['is_active' => false]);
    app('auth')->forgetGuards();

    $this->get('/settings/department/'.$f->sales->id)->assertRedirect(route('login'));
});

test('the session cookie is HttpOnly and SameSite=Lax', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.driver'))->not->toBe('cookie');
});
