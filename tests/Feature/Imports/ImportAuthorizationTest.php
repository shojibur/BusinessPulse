<?php

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\SalesRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// Property 8: another department's import is a 404, Staff a 403, Owner and own-department Manager are permitted.

function makeImport($department, $uploader, ImportStatus $status = ImportStatus::Validated, array $extra = []): Import
{
    return Import::create([
        'company_id' => $department->company_id,
        'department_id' => $department->id,
        'uploaded_by' => $uploader->id,
        'original_filename' => 'fictional.csv',
        'stored_path' => 'imports/secret/never-exposed.csv',
        'file_hash' => str_repeat('a', 64),
        'status' => $status,
        'total_rows' => 1,
        'valid_rows' => 1,
        'inserted_rows' => 1,
        ...$extra,
    ]);
}

beforeEach(function () {
    $this->f = identity();
    Storage::fake('local');
    $this->salesImport = makeImport($this->f->sales, $this->f->owner);
    $this->supportImport = makeImport($this->f->support, $this->f->owner);
});

test('Staff are denied every import route', function () {
    $this->actingAs($this->f->staff);
    $id = $this->salesImport->id;

    foreach ([['GET', '/imports'], ['GET', '/imports/create'], ['GET', '/imports/template'], ['GET', "/imports/{$id}"],
        ['POST', '/imports'], ['POST', "/imports/{$id}/confirm"], ['POST', "/imports/{$id}/cancel"], ['POST', "/imports/{$id}/retry"]] as [$method, $path]) {
        $this->call($method, $path)->assertForbidden();
    }

    expect($this->salesImport->fresh()->status)->toBe(ImportStatus::Validated);
});

test('a Manager sees only own department imports and gets 404 for another department', function () {
    $this->actingAs($this->f->manager);

    $this->get('/imports')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('imports', 1)->where('imports.0.id', $this->salesImport->id));
    $this->get("/imports/{$this->salesImport->id}")->assertOk();

    foreach ([['GET', ''], ['POST', '/confirm'], ['POST', '/cancel'], ['POST', '/retry']] as [$method, $suffix]) {
        $this->call($method, "/imports/{$this->supportImport->id}{$suffix}")->assertNotFound();
    }

    $this->get('/imports/999999')->assertNotFound();
    expect($this->supportImport->fresh()->status)->toBe(ImportStatus::Validated);

    // The other department's manager sees the mirror image.
    $this->actingAs($this->f->otherManager)->get("/imports/{$this->salesImport->id}")->assertNotFound();
    $this->actingAs($this->f->otherManager)->get("/imports/{$this->supportImport->id}")->assertOk();
});

test('an in-scope Manager may confirm, cancel and retry through the actions', function () {
    $this->actingAs($this->f->manager);
    Queue::fake();

    $this->post("/imports/{$this->salesImport->id}/confirm")->assertRedirect(route('imports.show', $this->salesImport));
    expect($this->salesImport->fresh()->status)->toBe(ImportStatus::Processing);

    $other = makeImport($this->f->sales, $this->f->owner);
    $this->post("/imports/{$other->id}/cancel")->assertRedirect();
    expect($other->fresh()->status)->toBe(ImportStatus::Cancelled);

    $failed = makeImport($this->f->sales, $this->f->owner, ImportStatus::Failed, ['error_message' => 'x']);
    Storage::disk('local')->put($failed->stored_path, 'sales_date');
    $this->post("/imports/{$failed->id}/retry")->assertRedirect();
    expect($failed->fresh()->status)->toBe(ImportStatus::Processing);
});

test('the import detail never exposes the stored path or any absolute path', function () {
    $content = $this->actingAs($this->f->owner)->get("/imports/{$this->salesImport->id}")->getContent();

    expect($content)->not->toContain('stored_path')->not->toContain('never-exposed')->not->toContain(storage_path());

    $index = $this->get('/imports')->getContent();
    expect($index)->not->toContain('stored_path')->not->toContain('never-exposed');
});

test('an upload rejection is a validation error on the file field and creates nothing', function () {
    $before = Import::count();

    $this->actingAs($this->f->owner)->post('/imports', ['file' => csvUpload("a,b\n1,2\n")])
        ->assertSessionHasErrors('file');
    expect(session('errors')->first('file'))->toContain('missing')->toContain('sales_date,branch_code');

    $this->post('/imports', [])->assertSessionHasErrors('file');
    expect(Import::count())->toBe($before);
});

test('sales records are scoped by the department of their import and are read only for everyone', function () {
    $mk = fn ($department, $import) => SalesRecord::create([
        'department_id' => $department->id, 'import_id' => $import->id, 'branch_code' => 'KL', 'sales_date' => '2026-09-21',
        'revenue' => '1.00', 'target_revenue' => '1.00', 'is_active' => true,
    ]);
    $own = $mk($this->f->sales, $this->salesImport);
    $foreign = $mk($this->f->support, $this->supportImport);

    $visible = fn ($user) => SalesRecord::visibleTo($user)->pluck('id')->sort()->values()->all();

    expect($visible($this->f->owner))->toBe([$own->id, $foreign->id])
        ->and($visible($this->f->manager))->toBe([$own->id])
        ->and($visible($this->f->otherManager))->toBe([$foreign->id])
        ->and($visible($this->f->staff))->toBe([$own->id]) // Staff are further restricted by the policy below
        ->and($visible($this->f->inactive))->toBe([])
        ->and($visible($this->f->noDepartment))->toBe([]);

    expect(Gate::forUser($this->f->owner)->allows('view', $foreign))->toBeTrue()
        ->and(Gate::forUser($this->f->manager)->allows('view', $own))->toBeTrue()
        ->and(Gate::forUser($this->f->manager)->denies('view', $foreign))->toBeTrue()
        ->and(Gate::forUser($this->f->staff)->denies('viewAny', SalesRecord::class))->toBeTrue()
        ->and(Gate::forUser($this->f->staff)->denies('view', $own))->toBeTrue();

    foreach (['create', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect(Gate::forUser($this->f->owner)->denies($ability, $own))->toBeTrue();
    }
});
