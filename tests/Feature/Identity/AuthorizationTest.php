<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

// Property 2: out-of-scope records are 404, a forbidden action or section is 403.

test('Staff get 403 on the Company, Staff and Department tabs and on every manager-only section', function (string $path) {
    $f = identity();

    $this->actingAs($f->staff)->get($path)->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
})->with(['/settings/company', '/issues', '/imports', '/outcomes']);

test('Staff land on My Tasks (Today redirects there) and cannot open the management task list', function () {
    $this->actingAs(identity()->staff);

    $this->get('/today')->assertRedirect(route('tasks.mine'));
    $this->get('/my-tasks')->assertOk();
    $this->get('/tasks')->assertForbidden();
});

test('a Staff user cannot reach another departments record by URL either', function () {
    $f = identity();

    $this->actingAs($f->staff)->get('/settings/department/'.$f->support->id)->assertForbidden();
});

test('the Owner sees all tabs and every department and user of the company', function () {
    $f = identity();
    $this->actingAs($f->owner);

    $this->get('/settings/company')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Company')
            ->has('settingsTabs', 3)
            ->where('company.name', 'Fixture Trading Sdn Bhd')
            ->where('company.recording_retention_days', 30));

    $this->get('/settings/staff')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/Staff')->has('users', 7));

    $this->get('/settings/department/'.$f->support->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('department.name', 'Support'));
});

test('the Manager sees the Company read-only without the retention setting, and only their own department', function () {
    $f = identity();
    $this->actingAs($f->manager);

    $this->get('/settings/company')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('company.recording_retention_days', null)->has('company.trading_days'));

    $this->get('/settings/department')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('department.name', 'Sales')
            ->where('department.expected_branches', ['KL', 'PG', 'JB'])
            ->where('department.managers.0.name', 'Marcus Manager')
            ->where('department.active_staff_count', 1)
            ->where('department.staff.0.job_title', 'Sales Executive (KL)'));
});

test('a Manager gets 404 for a department of another department and 200 for their own', function () {
    $f = identity();
    $this->actingAs($f->manager);

    $this->get('/settings/department/'.$f->support->id)->assertNotFound();
    $this->get('/settings/department/999999')->assertNotFound();
    $this->get('/settings/department/'.$f->sales->id)->assertOk();
});

test('policies: only the active Owner administers accounts, a Manager can only view their own department', function () {
    $f = identity();

    foreach (['create'] as $ability) {
        expect(Gate::forUser($f->owner)->allows($ability, User::class))->toBeTrue()
            ->and(Gate::forUser($f->manager)->denies($ability, User::class))->toBeTrue()
            ->and(Gate::forUser($f->staff)->denies($ability, User::class))->toBeTrue();
    }

    foreach (['update', 'deactivate', 'reactivate', 'resetPassword'] as $ability) {
        expect(Gate::forUser($f->owner)->allows($ability, $f->staff))->toBeTrue()
            ->and(Gate::forUser($f->manager)->denies($ability, $f->staff))->toBeTrue()
            ->and(Gate::forUser($f->staff)->denies($ability, $f->staff))->toBeTrue();
    }

    expect(Gate::forUser($f->manager)->allows('view', $f->staff))->toBeTrue()
        ->and(Gate::forUser($f->manager)->denies('view', $f->otherStaff))->toBeTrue()
        ->and(Gate::forUser($f->manager)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($f->staff)->denies('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($f->manager)->allows('view', $f->company))->toBeTrue()
        ->and(Gate::forUser($f->manager)->denies('update', $f->company))->toBeTrue()
        ->and(Gate::forUser($f->owner)->allows('update', $f->company))->toBeTrue()
        ->and(Gate::forUser($f->staff)->denies('view', $f->company))->toBeTrue()
        ->and(Gate::forUser($f->manager)->allows('view', $f->sales))->toBeTrue()
        ->and(Gate::forUser($f->manager)->denies('view', $f->support))->toBeTrue()
        ->and(Gate::forUser($f->owner)->allows('view', $f->support))->toBeTrue();
});

test('policies deny every action to an inactive user, including an inactive Owner', function () {
    $f = identity();
    $f->owner->forceFill(['is_active' => false]);

    foreach (['create'] as $ability) {
        expect(Gate::forUser($f->owner)->denies($ability, User::class))->toBeTrue();
    }

    expect(Gate::forUser($f->owner)->denies('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($f->owner)->denies('update', $f->staff))->toBeTrue()
        ->and(Gate::forUser($f->owner)->denies('view', $f->company))->toBeTrue()
        ->and(Gate::forUser($f->owner)->denies('view', $f->sales))->toBeTrue()
        ->and(Gate::forUser($f->inactive)->denies('view', $f->company))->toBeTrue();
});
