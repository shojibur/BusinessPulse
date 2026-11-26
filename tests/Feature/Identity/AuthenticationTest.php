<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController as Auth;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Database\Factories\UserFactory;

function login(string $email, string $password = UserFactory::PASSWORD)
{
    return test()->post(route('login.store'), ['email' => $email, 'password' => $password]);
}

test('an active user can sign in, the session id is regenerated and Staff land on My Tasks', function () {
    $f = identity();
    $before = session()->getId();

    login($f->staff->email)->assertRedirect(route('tasks.mine'));

    $this->assertAuthenticatedAs($f->staff);
    expect(session()->getId())->not->toBe($before);
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

test('signed-in users are redirected away from the login page', function () {
    $this->actingAs(identity()->staff)->get(route('login'))->assertRedirect(route('tasks.mine'));
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

test('a deactivated user with an open session is signed out on the next request', function () {
    $f = identity();
    login($f->staff->email);
    $this->get('/my-tasks')->assertOk();

    User::whereKey($f->staff->id)->update(['is_active' => false]);
    app('auth')->forgetGuards(); // a new request loads the user from the database again

    $this->get('/today')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => EnsureUserIsActive::MESSAGE]);
    $this->assertGuest();
});
