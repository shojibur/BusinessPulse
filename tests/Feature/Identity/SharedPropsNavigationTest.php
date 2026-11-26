<?php

use Inertia\Testing\AssertableInertia as Assert;

test('Owner and Manager receive all nine navigation items, Staff only Today and My Tasks', function (string $user, array $labels) {
    $f = identity();

    $this->actingAs($f->{$user})->get($user === 'staff' ? '/my-tasks' : '/today')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($navigation) => collect($navigation)->pluck('label')->all() === $labels));
})->with([
    'owner' => ['owner', ['Today', 'Issues', 'Briefs', 'Meetings', 'Tasks', 'My Tasks', 'Outcomes', 'Data Imports', 'Settings']],
    'manager' => ['manager', ['Today', 'Issues', 'Briefs', 'Meetings', 'Tasks', 'My Tasks', 'Outcomes', 'Data Imports', 'Settings']],
    'staff' => ['staff', ['Today', 'My Tasks']],
]);

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
