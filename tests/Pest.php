<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Every feature test runs on the PostgreSQL test database (guarded in Tests\TestCase) and is rolled back.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Fictional fixture: one company with a Sales department (KL, PG, JB) and a second, test-only
 * Support department, with users in every role.
 */
function identity(): stdClass
{
    $f = new stdClass;

    $f->company = Company::factory()->create(['name' => 'Fixture Trading Sdn Bhd']);
    $f->sales = Department::factory()->create([
        'company_id' => $f->company->id,
        'name' => 'Sales',
        'slug' => 'sales',
        'settings' => ['expected_branches' => ['KL', 'PG', 'JB']],
    ]);
    $f->support = Department::factory()->create([
        'company_id' => $f->company->id,
        'name' => 'Support',
        'slug' => 'support',
    ]);

    $f->owner = User::factory()->owner()->create(['company_id' => $f->company->id, 'name' => 'Olivia Owner', 'email' => 'owner@fixture.test']);
    $f->manager = User::factory()->manager($f->sales)->create(['name' => 'Marcus Manager', 'email' => 'manager@fixture.test', 'manager_id' => $f->owner->id]);
    $f->staff = User::factory()->staff($f->sales)->create(['name' => 'Sara Staff', 'email' => 'staff@fixture.test', 'manager_id' => $f->manager->id, 'job_title' => 'Sales Executive (KL)']);
    $f->inactive = User::factory()->staff($f->sales)->inactive()->create(['name' => 'Ivan Inactive', 'email' => 'inactive@fixture.test', 'manager_id' => $f->manager->id]);
    $f->otherManager = User::factory()->manager($f->support)->create(['name' => 'Mona Manager', 'email' => 'other-manager@fixture.test']);
    $f->otherStaff = User::factory()->staff($f->support)->create(['name' => 'Omar Other', 'email' => 'other-staff@fixture.test', 'manager_id' => $f->otherManager->id]);
    $f->noDepartment = User::factory()->create(['company_id' => $f->company->id, 'role' => 'manager', 'department_id' => null, 'email' => 'nodept@fixture.test']);

    return $f;
}
