<?php

namespace App\Actions\Imports;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Services\Imports\ImportActionException;
use Illuminate\Support\Facades\DB;

final class CancelImport
{
    /**
     * Only uploaded and validated imports can be cancelled. The file is kept.
     *
     * @throws ImportActionException
     */
    public function handle(Import $import): Import
    {
        return DB::transaction(function () use ($import): Import {
            $locked = Import::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ImportStatus::Uploaded, ImportStatus::Validated], true)) {
                throw new ImportActionException("This import cannot be cancelled because it is {$locked->status->label()}.");
            }

            $locked->update(['status' => ImportStatus::Cancelled]);

            return $locked;
        });
    }
}
