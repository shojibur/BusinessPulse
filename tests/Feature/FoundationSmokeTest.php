<?php

use App\Support\Flash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('the application boots and the root redirects to Today', function () {
    $this->get('/')->assertRedirect('/today');
});

test('a guest is sent to the login page from Today', function () {
    $this->get('/today')->assertRedirect(route('login'));
});

test('the login page renders', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
});

test('every navigation item has a placeholder page and the shell shares all nine items', function () {
    $expected = ['Today', 'Issues', 'Data Imports', 'Briefs', 'Meetings', 'Tasks', 'My Tasks', 'Outcomes', 'Settings'];
    $this->actingAs(identity()->owner);

    foreach (config('navigation') as $item) {
        $this->followingRedirects()->get(route($item['route']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('navigation', 9)
                ->where('navigation.0.label', 'Today')
                ->where('navigation', fn ($navigation) => collect($navigation)->pluck('label')->all() === $expected)
                ->where('navigation', fn ($navigation) => collect($navigation)->where('active', true)->pluck('label')->all() === [$item['label']])
            );
    }
});

test('unknown routes and the storage path render the 404 Inertia error page', function (string $path) {
    $this->actingAs(identity()->owner)->get($path)
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404)->has('navigation', 9));
})->with(['/no-such-page', '/storage/anything.csv']);

test('a 403, 419, 500 and 503 render the Inertia error page without leaking details', function (int $status) {
    Route::middleware('web')->get("/_test/abort/{$status}", fn () => abort($status));

    $this->actingAs(identity()->owner)->get("/_test/abort/{$status}")
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', $status));
})->with([403, 419, 500, 503]);

test('json requests receive the standard JSON error structure', function () {
    Route::middleware('web')->get('/_test/json-abort', fn () => abort(403));

    $this->getJson('/_test/json-abort')->assertForbidden()->assertJsonStructure(['message']);
});

test('flash messages carry a unique id', function () {
    Route::middleware('web')->get('/_test/flash', function () {
        Flash::success('Saved');

        return redirect('/today');
    });

    $this->actingAs(identity()->owner)->followingRedirects()->get('/_test/flash')
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.type', 'success')
            ->where('flash.message', 'Saved')
            ->has('flash.id'));
});

test('without a flash message the shared flash prop is null', function () {
    $this->actingAs(identity()->owner)->get('/today')->assertInertia(fn (Assert $page) => $page->where('flash', null));
});

test('shared props never expose secrets', function () {
    config(['ai.api_key' => 'sk-secret-test', 'demo.password' => 'demo-secret-test']);

    $body = $this->actingAs(identity()->owner)->get('/today')->getContent();

    expect($body)->not->toContain('sk-secret-test')->not->toContain('demo-secret-test');
});
