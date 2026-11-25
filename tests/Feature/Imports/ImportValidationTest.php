<?php

use App\Actions\Imports\ConfirmImport;
use App\Actions\Imports\CreateImportFromUpload;
use App\Models\Import;
use App\Models\ImportError;
use App\Models\SalesRecord;
use App\Services\Imports\CsvImportReader;
use App\Services\Imports\ImportPreviewBuilder;
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

test('size and row limits come from the configuration', function () {
    config(['imports.max_file_bytes' => 100]);
    expect(fn () => createImport(HEADER.str_repeat("2026-09-21,KL,5.00,5.00,\n", 10)))->toThrow(fn (ImportRejectedException $e) => $e->reason === 'file_too_large');

    config(['imports.max_file_bytes' => 5 * 1024 * 1024, 'imports.max_rows' => 3]);
    expect(fn () => createImport(HEADER.str_repeat("2026-09-21,KL,5.00,5.00,\n", 4)))->toThrow(fn (ImportRejectedException $e) => $e->reason === 'too_many_rows');

    // Exactly at the limit is accepted.
    $rows = "2026-09-21,KL,5.00,5.00,\n2026-09-22,KL,5.00,5.00,\n2026-09-23,KL,5.00,5.00,\n";
    expect(createImport(HEADER.$rows)->total_rows)->toBe(3);
});

test('byte order mark, CRLF or CR line endings, header case, column order and blank lines are accepted', function (string $eol, string $prefix) {
    $csv = $prefix."Revenue , SALES_DATE,branch_code ,target_revenue{$eol}{$eol}100.5,2026-09-21,kl,200{$eol}   {$eol}0,2026-09-22,Pg,0.00{$eol}";

    $import = createImport($csv);

    expect([$import->total_rows, $import->valid_rows, $import->invalid_rows])->toBe([2, 2, 0]);
    $preview = app(ImportPreviewBuilder::class)->build($import->refresh());
    expect($preview->branches)->toBe(['KL', 'PG']);
})->with([
    'LF' => ["\n", ''],
    'CRLF' => ["\r\n", ''],
    'CR only' => ["\r", ''],
    'BOM' => ["\n", "\xEF\xBB\xBF"],
]);

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

test('two rows with the same identity are both invalid and the counters stay consistent', function () {
    $import = createImport(HEADER."2026-09-21,KL,100.00,200.00,\n2026-09-21,kl,150.00,200.00,\n2026-09-22,KL,100.00,200.00,\n2026-09-21,KL,1.00,2.00,\n");

    expect($import->total_rows)->toBe(4)->and($import->valid_rows)->toBe(1)->and($import->invalid_rows)->toBe(3)
        ->and($import->total_rows)->toBe($import->valid_rows + $import->invalid_rows)
        ->and($import->valid_rows)->toBe($import->inserted_rows + $import->corrected_rows + $import->unchanged_rows)
        ->and(errorCodes($import))->toBe(['2:sales_date:duplicate_in_file', '3:sales_date:duplicate_in_file', '5:sales_date:duplicate_in_file']);

    $first = $import->errors()->where('row_number', 2)->first();
    expect($first->message)->toContain('rows 3, 5')
        ->and($import->errors()->where('row_number', 3)->first()->message)->toContain('rows 2, 5');
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

test('a null order count equals only a null order count', function () {
    $seed = createImport(HEADER."2026-09-21,KL,100.00,200.00,\n");
    app(ConfirmImport::class)->handle($seed);

    expect(createImport(HEADER."2026-09-21,KL,100.00,200.00,\n")->unchanged_rows)->toBe(1)
        ->and(createImport(HEADER."2026-09-21,KL,100.00,200.00,0\n")->corrected_rows)->toBe(1);
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

test('the preview separates trading and non trading days, weeks and uncovered due days', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');
    setClock('2026-09-28 09:00');
    Storage::disk('local')->put('keep', '1');

    // Re-create the history rows on the faked disk is not needed: records live in the database.
    $import = app(CreateImportFromUpload::class)->handle($this->f->owner, csvUpload(HEADER."2026-09-27,KL,10.00,20.00,\n2026-09-21,KL,9539.00,12000.00,\n"));
    $preview = app(ImportPreviewBuilder::class)->build($import->refresh())->toArray();

    expect($preview['counts'])->toBe(['total' => 2, 'valid' => 2, 'invalid' => 0, 'new' => 2, 'corrected' => 0, 'unchanged' => 0])
        ->and($preview['non_trading_day_rows'])->toBe(1) // the Sunday row is valid and stored but ignored for completeness
        ->and($preview['min_date'])->toBe('2026-09-21')->and($preview['max_date'])->toBe('2026-09-27')
        ->and($preview['weeks'])->toHaveCount(1)
        ->and($preview['weeks'][0]['status'])->toBe('closed')->and($preview['weeks'][0]['completeness'])->toBe('incomplete')
        ->and($preview['weeks'][0]['gaps'])->toHaveCount(17) // 18 expected branch-days, 1 present
        ->and($preview['uncovered']['last_due_date'])->toBe('2026-09-26')
        ->and($preview['uncovered']['overdue_branch_days'])->toBe(0); // the pending Sunday row moves the latest date past 26 Sep
});

test('the preview is refreshed when the active records changed after validation', function () {
    importFixture($this->f->owner, 'history', '2026-09-21 09:00');
    setClock('2026-09-28 09:00');
    $a = app(CreateImportFromUpload::class)->handle($this->f->owner, fixtureUpload('week8-decline'));
    expect(app(ImportPreviewBuilder::class)->build($a)->refreshed)->toBeFalse();

    $b = app(CreateImportFromUpload::class)->handle($this->f->owner, fixtureUpload('week8-decline'));
    app(ConfirmImport::class)->handle($b);

    $preview = app(ImportPreviewBuilder::class)->build($a->refresh());
    expect($preview->refreshed)->toBeTrue()->and($preview->counts['unchanged'])->toBe(18)->and($preview->counts['new'])->toBe(0);
});

test('the original file name is sanitized and never used in the storage path', function () {
    $import = createImport(HEADER."2026-09-21,KL,1.00,1.00,\n", "../../etc/pass\x00wd<script>.csv");

    expect($import->original_filename)->not->toContain('/')->not->toContain("\x00")
        ->and($import->stored_path)->toStartWith("imports/{$import->id}/")->toEndWith('.csv')->not->toContain('etc')->not->toContain('script');
});

test('no public storage link or public url exists for imports', function () {
    $import = createImport(HEADER."2026-09-21,KL,1.00,1.00,\n");

    expect(is_link(public_path('storage')))->toBeFalse()
        ->and(config('filesystems.disks.local.url'))->toBeNull()
        ->and(file_exists(public_path($import->stored_path)))->toBeFalse();
    $this->actingAs($this->f->owner)->get('/storage/'.$import->stored_path)->assertNotFound();
});

test('the reader streams an upload of several thousand rows', function () {
    $rows = '';
    for ($i = 0; $i < 6000; $i++) {
        $rows .= '2026-09-'.str_pad((string) (($i % 20) + 1), 2, '0', STR_PAD_LEFT).','.['KL', 'PG', 'JB'][$i % 3].','.$i.".00,100.00,\n";
    }

    $import = createImport(HEADER.$rows);

    // Identities repeat (20 dates x 3 branches), so most rows are duplicates; the invariants must still hold.
    expect($import->total_rows)->toBe(6000)
        ->and($import->total_rows)->toBe($import->valid_rows + $import->invalid_rows)
        ->and($import->valid_rows)->toBe($import->inserted_rows + $import->corrected_rows + $import->unchanged_rows);
});

test('the template header matches the single column specification', function () {
    $reader = app(CsvImportReader::class);

    expect($reader->templateHeader())->toBe('sales_date,branch_code,revenue,target_revenue,order_count');

    $this->actingAs($this->f->owner)->get(route('imports.template'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('sales-import-template.csv');
});

test('upload as an UploadedFile fake keeps the stored file identical to the upload', function () {
    $content = HEADER."2026-09-21,KL,1.00,1.00,\n";
    $import = createImport($content);

    expect(Storage::disk('local')->get($import->stored_path))->toBe($content)
        ->and($import->file_hash)->toBe(hash('sha256', $content));
});
