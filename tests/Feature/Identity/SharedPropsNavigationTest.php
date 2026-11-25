<?php

use Inertia\Testing\AssertableInertia as Assert;

test('Owner and Manager receive all nine navigation items, Staff only Today and My Tasks', function (string $user, array $labels) {
    $f = identity();

    $this->actingAs($f->{$user})->get($user === 'staff' ? '/my-tasks' : '/today')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($navigation) => collect($navigation)->pluck('label')->all() === $labels));
})->with([
    'owner' => ['owner', ['Today', 'Issues', 'Data Imports', 'Briefs', 'Meetings', 'Tasks', 'My Tasks', 'Outcomes', 'Settings']],
    'manager' => ['manager', ['Today', 'Issues', 'Data Imports', 'Briefs', 'Meetings', 'Tasks', 'My Tasks', 'Outcomes', 'Settings']],
    'staff' => ['staff', ['Today', 'My Tasks']],
]);

test('the Settings navigation item links to the first permitted tab', function () {
    $f = identity();

    $this->actingAs($f->manager)->get('/today')
        ->assertInertia(fn (Assert $page) => $page->where('navigation.8.href', '/settings'));

    $this->actingAs($f->manager)->get('/settings')->assertRedirect('/settings/company');
});

test('auth.user shares only the approved fields', function () {
    $f = identity();

    $this->actingAs($f->manager)->get('/today')->assertInertia(fn (Assert $page) => $page
        ->where('auth.role', 'manager')
        ->where('auth.user', fn ($user) => collect($user)->keys()->sort()->values()->all() === ['department', 'email', 'id', 'job_title', 'name', 'role']
            && $user['role'] === 'manager'
            && collect($user['department'])->keys()->sort()->values()->all() === ['id', 'name'])
        ->where('company', fn ($company) => collect($company)->keys()->sort()->values()->all() === ['currency', 'name', 'timezone', 'trading_days']));
});

test('shared props never contain secrets, other users or internal fields', function () {
    $f = identity();

    $content = $this->actingAs($f->staff)->get('/today')->getContent();

    expect($content)
        ->not->toContain('remember_token')
        ->not->toContain('manager_id')
        ->not->toContain('is_active')
        ->not->toContain('"password"')
        ->not->toContain('$2y$')
        ->not->toContain($f->owner->email)
        ->not->toContain($f->manager->email);
});

test('guests receive no auth user and no company', function () {
    identity();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('auth.user', null)->where('company', null));
});

test('a signed-in user sees the application shell data on a 404 page', function () {
    $f = identity();

    $this->actingAs($f->staff)->get('/nowhere')->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('auth.role', 'staff')->has('navigation', 2));
});

test('a guest sees a 404 page without navigation', function () {
    identity();

    $this->get('/nowhere')->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('auth.user', null)->has('navigation', 0));
});
