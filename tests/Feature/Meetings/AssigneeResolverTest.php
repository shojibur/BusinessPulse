<?php

use App\Enums\AssigneeResolution;
use App\Models\Department;
use App\Models\User;
use App\Services\Meetings\AssigneeResolver;

beforeEach(function () {
    $this->f = meetingRoster();
    $this->staff = User::where('department_id', $this->f->sales->id)->where('is_active', true)->whereIn('role', ['staff', 'manager'])->get();
    $this->resolver = app(AssigneeResolver::class);
});

test('a unique first name resolves to that user', function () {
    $r = $this->resolver->resolve('Priya', $this->staff);

    expect($r->resolution)->toBe(AssigneeResolution::Resolved)->and($r->userId)->toBe($this->f->priya->id);
    expect($this->resolver->resolve('  FARID ', $this->staff)->userId)->toBe($this->f->farid->id);
});

test('a first name shared by two people is ambiguous and never guessed', function () {
    $r = $this->resolver->resolve('Daniel', $this->staff);

    expect($r->resolution)->toBe(AssigneeResolution::Ambiguous)->and($r->userId)->toBeNull();
    expect($this->resolver->resolve('Daniel Tan', $this->staff)->userId)->toBe($this->f->danielTan->id);
});

test('unknown names are unmatched and a missing name is none', function () {
    expect($this->resolver->resolve('Zaid', $this->staff)->resolution)->toBe(AssigneeResolution::Unmatched)
        ->and($this->resolver->resolve(null, $this->staff)->resolution)->toBe(AssigneeResolution::None)
        ->and($this->resolver->resolve('   ', $this->staff)->resolution)->toBe(AssigneeResolution::None);
});

test('the owner is never an assignee, even when named or when an instruction asks for the owner', function () {
    foreach (['Lim Wei Han', 'Wei Han', 'the owner', 'Owner'] as $name) {
        $r = $this->resolver->resolve($name, $this->staff);
        expect($r->resolution)->toBe(AssigneeResolution::Unmatched)->and($r->userId)->toBeNull();
    }
});

test('people of other departments and inactive users are not candidates', function () {
    $other = User::factory()->staff(Department::factory()->create(['company_id' => $this->f->company->id, 'slug' => 'support']))->create(['name' => 'Zainab Other']);
    $inactive = User::factory()->staff($this->f->sales)->inactive()->create(['name' => 'Ivan Gone']);
    $staff = User::where('department_id', $this->f->sales->id)->where('is_active', true)->whereIn('role', ['staff', 'manager'])->get();

    expect($staff->pluck('id'))->not->toContain($other->id)->not->toContain($inactive->id)
        ->and($this->resolver->resolve('Zainab', $staff)->resolution)->toBe(AssigneeResolution::Unmatched)
        ->and($this->resolver->resolve('Ivan', $staff)->resolution)->toBe(AssigneeResolution::Unmatched);
});
