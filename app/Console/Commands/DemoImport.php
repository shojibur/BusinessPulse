<?php

namespace App\Console\Commands;

use App\Actions\Imports\ConfirmImport;
use App\Actions\Imports\CreateImportFromUpload;
use App\Enums\ImportStatus;
use App\Models\Company;
use App\Models\Import;
use App\Models\User;
use App\Services\Imports\ImportActionException;
use App\Services\Imports\ImportRejectedException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

#[Signature('demo:import {fixture : history, week8-decline, week8-with-errors, week8-correction, week9-interim or week10-recovery}')]
#[Description('Import a demo CSV fixture through the real upload, confirm and processing path (requires DEMO_MODE=true)')]
class DemoImport extends Command
{
    private const FIXTURES = ['history', 'week8-decline', 'week8-with-errors', 'week8-correction', 'week9-interim', 'week10-recovery'];

    public function handle(CreateImportFromUpload $create, ConfirmImport $confirm): int
    {
        if (! config('demo.mode')) {
            $this->components->error('demo:import requires DEMO_MODE=true. Nothing was imported.');

            return self::FAILURE;
        }

        $name = preg_replace('/\.csv$/i', '', (string) $this->argument('fixture'));

        if (! in_array($name, self::FIXTURES, true)) {
            $this->components->error("Unknown fixture [{$name}]. Use one of: ".implode(', ', self::FIXTURES).'.');

            return self::FAILURE;
        }

        $company = Company::query()->where('is_demo', true)->first();
        $owner = $company === null ? null : User::query()->where('company_id', $company->id)->where('role', 'owner')->where('is_active', true)->first();

        if ($owner === null) {
            $this->components->error('The demo company and its Owner do not exist. Run: php artisan db:seed --class=DemoIdentitySeeder');

            return self::FAILURE;
        }

        $expected = json_decode((string) file_get_contents(base_path('database/demo/expected.json')), true, 512, JSON_THROW_ON_ERROR)['fixtures'][$name];
        $path = base_path("database/demo/{$name}.csv");

        // The processing job runs inline through the very same class as in production; nothing is inserted directly.
        config(['queue.default' => 'sync']);

        try {
            $import = $create->handle($owner, new UploadedFile($path, "{$name}.csv", 'text/csv', null, true));
        } catch (ImportRejectedException $e) {
            $this->components->error("Upload rejected ({$e->reason}): {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($import->status === ImportStatus::Validated) {
            try {
                $import = $confirm->handle($import);
            } catch (ImportActionException $e) {
                // Nothing valid to import, usually because the demo clock is before the fixture dates.
                $this->components->error($e->getMessage());
                $this->line('If the dates are in the future, set the demo clock first: php artisan demo:set-date '.substr($expected['clock'], 0, 10));

                return self::FAILURE;
            }
        }

        $import = Import::query()->findOrFail($import->id);

        $this->line("Import #{$import->id}: {$import->status->value}");
        $this->line("rows total {$import->total_rows}, valid {$import->valid_rows}, invalid {$import->invalid_rows}; new {$import->inserted_rows}, corrected {$import->corrected_rows}, unchanged {$import->unchanged_rows}");

        foreach ($import->errors()->orderBy('row_number')->get() as $error) {
            $this->line("  row {$error->row_number} {$error->column}: {$error->code}");
        }

        if ($import->error_message !== null) {
            $this->line("  error: {$import->error_message}");
        }

        $finished = $import->status->isFinished();
        $matches = $import->total_rows === $expected['totalRows']
            && $import->valid_rows === $expected['validRows']
            && $import->invalid_rows === $expected['invalidRows'];

        if (! $finished || ! $matches) {
            $this->components->error($finished
                ? "Counters differ from expected.json (expected total {$expected['totalRows']}, valid {$expected['validRows']}, invalid {$expected['invalidRows']})."
                : 'The import did not complete.');
            $finished || $this->line('If a date is in the future, set the demo clock first: php artisan demo:set-date '.substr($expected['clock'], 0, 10));

            return self::FAILURE;
        }

        $this->components->info('Imported through the real import workflow.');

        return self::SUCCESS;
    }
}
