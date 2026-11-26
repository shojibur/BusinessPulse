<?php

use App\Actions\Imports\ConfirmImport;
use App\Actions\Imports\CreateImportFromUpload;
use App\Models\Import;
use App\Models\ImportError;
use App\Models\SalesRecord;
use App\Services\Imports\ImportRejectedException;
use App\Services\Imports\ImportRowValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

const HEADER = "sales_date,branch_code,revenue,target_revenue,order_count\n";

beforeEach(function () {
    $this->f = identity();
    setClock('2026-09-28 09:00');
});

function createImport(string $csv, string $name = 'sales.csv'): Import
{
    return app(CreateImportFromUpload::class)->handle(test()->f->owner, csvUpload($csv, $name));
}

function errorCodes(Import $import): array
{
    return $import->errors()->orderBy('row_number')->orderBy('id')->get()->map(fn (ImportError $e) => "{$e->row_number}:{$e->column}:{$e->code}")->all();
}

test('file level problems are rejected with a code, no import row and no stored file', function (string $csv, string $name, string $code) {
    expect(fn () => createImport($csv, $name))->toThrow(fn (ImportRejectedException $e) => $e->reason === $code && str_contains($e->getMessage(), 'sales_date,branch_code,revenue,target_revenue,order_count'));

    expect(Import::count())->toBe(0)->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
})->with([
    'wrong extension' => [HEADER, 'sales.txt', 'invalid_extension'],
    'empty file' => ['', 'sales.csv', 'empty_file'],
    'blank file' => ["\n\n  \n", 'sales.csv', 'empty_file'],
    'not utf-8' => [HEADER."2026-09-21,KL,\xFF\xFE,5,\n", 'sales.csv', 'invalid_encoding'],
    'missing column' => ["sales_date,branch_code,revenue\n2026-09-21,KL,5\n", 'sales.csv', 'missing_columns'],
    'unexpected column' => ["sales_date,branch_code,revenue,target_revenue,region\n2026-09-21,KL,5,5,north\n", 'sales.csv', 'unexpected_columns'],
    'duplicate column' => ["sales_date,branch_code,revenue,revenue,target_revenue\n2026-09-21,KL,5,5,5\n", 'sales.csv', 'duplicate_header'],
]);

test('a binary file named .csv is rejected by the server detected type', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    expect(fn () => createImport($png, 'sales.csv'))->toThrow(fn (ImportRejectedException $e) => $e->reason === 'invalid_mime');
    expect(Import::count())->toBe(0);
});

test('every row level code is reported once per problem in column order', function () {
    $csv = HEADER.implode("\n", [
        ',KL,5,5,',                          // 2 missing sales_date
        '2026-02-30,KL,5,5,',                // 3 invalid_date
        '2026-10-01,KL,5,5,',                // 4 future_date (today is 2026-09-28)
        '1999-12-31,KL,5,5,',                // 5 date_out_of_range
        '2026-09-21,K L,5,5,',               // 6 invalid_branch_format
        '2026-09-21,XX,5,5,',                // 7 unknown_branch
        '2026-09-21,KL,-5,5,',               // 8 negative_value
        '2026-09-21,KL,5.123,5,',            // 9 too_many_decimals
        '2026-09-21,KL,1234567890123,5,',    // 10 value_out_of_range
        '2026-09-21,KL,abc,5,',              // 11 invalid_number
        '2026-09-21,KL,1,000.00,5,',         // 12 wrong_column_count
        '2026-09-21,KL,5,5,1.5',             // 13 invalid_integer
        '2026-09-21,KL,5,,',                 // 14 missing_value target
        'x,xx,-1,abc,z',                     // 15 many problems, column order
        '',
    ]);

    expect(errorCodes(createImport($csv)))->toBe([
        '2:sales_date:missing_value',
        '3:sales_date:invalid_date',
        '4:sales_date:future_date',
        '5:sales_date:date_out_of_range',
        '6:branch_code:invalid_branch_format',
        '7:branch_code:unknown_branch',
        '8:revenue:negative_value',
        '9:revenue:too_many_decimals',
        '10:revenue:value_out_of_range',
        '11:revenue:invalid_number',
        '12::wrong_column_count',
        '13:order_count:invalid_integer',
        '14:target_revenue:missing_value',
        '15:sales_date:invalid_date',
        '15:branch_code:unknown_branch',
        '15:revenue:negative_value',
        '15:target_revenue:invalid_number',
        '15:order_count:invalid_integer',
    ]);
});

test('zero revenue is a valid present value and money never becomes a float', function () {
    $import = createImport(HEADER."2026-09-21,KL,0,0.00,\n2026-09-21,PG,0.00,8000,12\n2026-09-21,JB,4520.5,6000.10,\n");

    expect($import->valid_rows)->toBe(3);

    $validator = app(ImportRowValidator::class);
    $row = $validator->validate(2, ['sales_date' => '2026-09-21', 'branch_code' => 'kl', 'revenue' => '4520.5', 'target_revenue' => '0.1', 'order_count' => ''], ['KL'], CarbonImmutable::parse('2026-09-28', 'UTC'))->row;

    expect($row->revenue)->toBeString()->toBe('4520.50')
        ->and($row->targetRevenue)->toBe('0.10')
        ->and($row->branchCode)->toBe('KL')
        ->and($row->orderCount)->toBeNull();
});

test('rows are classified new, corrected and unchanged and the counters satisfy the invariants', function () {
    $seed = createImport(HEADER."2026-09-21,KL,100.00,200.00,5\n2026-09-21,PG,100.00,200.00,\n2026-09-21,JB,100.00,200.00,\n");
    app(ConfirmImport::class)->handle($seed);

    $import = createImport(HEADER.implode("\n", [
        '2026-09-21,KL,100,200.0,5',      // unchanged (100 = 100.00, 200.0 = 200.00)
        '2026-09-21,PG,100.01,200.00,',   // corrected revenue
        '2026-09-21,JB,100.00,200.00,7',  // corrected: null order count differs from 7
        '2026-09-22,KL,50.00,60.00,',     // new
        'bad,KL,1,1,',                    // invalid
    ])."\n");

    expect([$import->inserted_rows, $import->corrected_rows, $import->unchanged_rows, $import->invalid_rows])->toBe([1, 2, 1, 1])
        ->and($import->total_rows)->toBe($import->valid_rows + $import->invalid_rows)
        ->and($import->valid_rows)->toBe($import->inserted_rows + $import->corrected_rows + $import->unchanged_rows);
});

test('a branch outside the expected branches is an unknown_branch row error and nothing derives the list', function () {
    $import = createImport(HEADER."2026-09-21,XX,100.00,200.00,\n2026-09-21,KL,100.00,200.00,\n");

    expect(errorCodes($import))->toBe(['2:branch_code:unknown_branch'])
        ->and($import->valid_rows)->toBe(1);

    app(ConfirmImport::class)->handle($import);

    expect($this->f->sales->fresh()->settings['expected_branches'])->toBe(['KL', 'PG', 'JB'])
        ->and(SalesRecord::where('branch_code', 'XX')->count())->toBe(0);
});

test('a department without expected branches rejects the upload before the file is read', function () {
    $this->f->sales->update(['settings' => []]);

    expect(fn () => createImport(HEADER."2026-09-21,KL,100.00,200.00,\n"))
        ->toThrow(fn (ImportRejectedException $e) => $e->reason === 'no_expected_branches');

    // Even a file that would be rejected for another reason reports the missing configuration first.
    expect(fn () => createImport('', 'x.txt'))->toThrow(fn (ImportRejectedException $e) => $e->reason === 'no_expected_branches');

    expect(Import::count())->toBe(0)->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

test('the original file name is sanitized and never used in the storage path', function () {
    $import = createImport(HEADER."2026-09-21,KL,1.00,1.00,\n", "../../etc/pass\x00wd<script>.csv");

    expect($import->original_filename)->not->toContain('/')->not->toContain("\x00")
        ->and($import->stored_path)->toStartWith("imports/{$import->id}/")->toEndWith('.csv')->not->toContain('etc')->not->toContain('script');
});
