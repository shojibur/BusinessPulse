<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\User;

// Property 1: R is returned by visibleTo(U) iff U is active and is the Owner of R's company, or U's
// department_id equals R's department_id.

function ids($query): array
{
    $ids = $query->pluck('id')->all();
    sort($ids);

    return $ids;
}

test('User::visibleTo returns the right rows for every kind of user', function () {
    $f = identity();

    $all = [$f->owner, $f->manager, $f->staff, $f->inactive, $f->otherManager, $f->otherStaff, $f->noDepartment];
    $sales = [$f->manager, $f->staff, $f->inactive];
    $support = [$f->otherManager, $f->otherStaff];
    $idsOf = function (array $users): array {
        $ids = array_map(fn (User $user) => $user->id, $users);
        sort($ids);

        return $ids;
    };

    expect(ids(User::visibleTo($f->owner)))->toBe($idsOf($all))
        ->and(ids(User::visibleTo($f->manager)))->toBe($idsOf($sales))
        ->and(ids(User::visibleTo($f->staff)))->toBe($idsOf($sales))
        ->and(ids(User::visibleTo($f->otherManager)))->toBe($idsOf($support))
        ->and(ids(User::visibleTo($f->otherStaff)))->toBe($idsOf($support))
        ->and(ids(User::visibleTo($f->inactive)))->toBe([])
        ->and(ids(User::visibleTo($f->noDepartment)))->toBe([]);
});

test('an inactive Owner sees nothing', function () {
    $f = identity();
    $f->owner->forceFill(['is_active' => false])->save();

    expect(ids(User::visibleTo($f->owner->fresh())))->toBe([]);
});

test('the Owner of one company never sees another company', function () {
    $f = identity();
    $otherCompany = Company::factory()->create();
    $otherDepartment = Department::factory()->create(['company_id' => $otherCompany->id]);
    $otherUser = User::factory()->staff($otherDepartment)->create();

    expect(ids(User::visibleTo($f->owner)))->not->toContain($otherUser->id)
        ->and(ids(User::visibleTo($otherUser)))->toBe([$otherUser->id]);
});

test('Department::visibleTo follows the same rule', function () {
    $f = identity();

    expect(ids(Department::visibleTo($f->owner)))->toBe([$f->sales->id, $f->support->id])
        ->and(ids(Department::visibleTo($f->manager)))->toBe([$f->sales->id])
        ->and(ids(Department::visibleTo($f->staff)))->toBe([$f->sales->id])
        ->and(ids(Department::visibleTo($f->inactive)))->toBe([])
        ->and(ids(Department::visibleTo($f->noDepartment)))->toBe([]);
});
