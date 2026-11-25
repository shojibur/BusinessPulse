<?php

namespace App\Actions\Imports;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Services\Imports\ImportActionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class RetryImport
{
    /**
     * @throws ImportActionException
     */
    public function handle(Import $import): Import
    {
        return DB::transaction(function () use ($import): Import {
            $locked = Import::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ImportStatus::Failed) {
                throw new ImportActionException("Only a failed import can be retried; this one is {$locked->status->label()}.");
            }

            if ($locked->stored_path === '' || ! Storage::disk('local')->exists($locked->stored_path)) {
                throw new ImportActionException('The source file is no longer available, so this import cannot be retried. Please upload the file again.');
            }

            $locked->update(['status' => ImportStatus::Processing, 'error_message' => null]);

            ProcessImportJob::dispatch($locked->id);

            return $locked;
        });
    }
}
