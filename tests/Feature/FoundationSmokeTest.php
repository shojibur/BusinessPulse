<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the application boots and the root redirects to Today', function () {
    $this->get('/')->assertRedirect('/today');
});

test('a guest is sent to the login page from Today', function () {
    $this->get('/today')->assertRedirect(route('login'));
});

test('every navigation item has a placeholder page and the shell shares all nine items', function () {
    $expected = ['Today', 'Issues', 'Briefs', 'Meetings', 'Tasks', 'My Tasks', 'Outcomes', 'Data Imports', 'Settings'];
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

test('shared props never expose secrets', function () {
    config(['ai.api_key' => 'sk-secret-test', 'demo.password' => 'demo-secret-test']);

    $body = $this->actingAs(identity()->owner)->get('/today')->getContent();

    expect($body)->not->toContain('sk-secret-test')->not->toContain('demo-secret-test');
});
