<?php

namespace App\Actions\Imports;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Services\Imports\ImportActionException;
use Illuminate\Support\Facades\DB;

final class ConfirmImport
{
    /**
     * Lock the import, move it to processing and queue the job once. A second confirm (double click) returns
     * the current import without dispatching again.
     *
     * @throws ImportActionException when a validated import has no valid row
     */
    public function handle(Import $import): Import
    {
        return DB::transaction(function () use ($import): Import {
            $locked = Import::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ImportStatus::Validated) {
                return $locked;
            }

            if ($locked->valid_rows < 1) {
                throw new ImportActionException('This file has no valid rows, so there is nothing to import.');
            }

            $locked->update(['status' => ImportStatus::Processing, 'confirmed_at' => now()]);

            // The job dispatches after this transaction commits.
            ProcessImportJob::dispatch($locked->id);

            return $locked;
        });
    }
}
