<?php

namespace App\Actions\Imports;

use App\Enums\ImportRowResult;
use App\Enums\ImportStatus;
use App\Events\Imports\ImportFailed;
use App\Models\Department;
use App\Models\Import;
use App\Models\User;
use App\Services\Imports\CsvImportReader;
use App\Services\Imports\ExpectedBranches;
use App\Services\Imports\ImportPreviewBuilder;
use App\Services\Imports\ImportRejectedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Upload -> file checks -> private storage -> row validation and classification -> validated import.
 * Writes no sales records and never changes the expected branches.
 */
final class CreateImportFromUpload
{
    public function __construct(
        private readonly CsvImportReader $reader,
        private readonly ExpectedBranches $expectedBranches,
        private readonly ImportPreviewBuilder $previews,
    ) {}

    /**
     * @throws ImportRejectedException when the file or the department configuration is rejected (nothing is created)
     */
    public function handle(User $user, UploadedFile $file): Import
    {
        $department = $this->targetDepartment($user);

        // Checked before the file is read: without expected branches coverage can never be judged.
        if ($this->expectedBranches->get($department) === []) {
            throw new ImportRejectedException(
                'no_expected_branches',
                'Expected branches must be configured for this department before sales data can be imported.',
            );
        }

        $this->reader->validate($file);

        // company_id, department_id and uploaded_by always come from the signed-in user, never from the request.
        $import = Import::create([
            'company_id' => $user->company_id,
            'department_id' => $department->id,
            'uploaded_by' => $user->id,
            'original_filename' => $this->sanitizeFilename($file->getClientOriginalName()),
            'status' => ImportStatus::Uploaded,
        ]);

        try {
            $stored = $file->storeAs(
                config('imports.storage_directory').'/'.$import->id,
                Str::random(40).'.csv',
                'local',
            );

            $import->update([
                'stored_path' => $stored,
                'file_hash' => hash_file('sha256', Storage::disk('local')->path($stored)),
            ]);

            $analysis = $this->previews->analyze($import->load(['department', 'company']));

            DB::transaction(function () use ($import, $analysis): void {
                $now = now();
                foreach (array_chunk($analysis->errors, 500) as $chunk) {
                    DB::table('import_errors')->insert(array_map(fn ($error) => [
                        'import_id' => $import->id,
                        'row_number' => $error->rowNumber,
                        'column' => $error->column,
                        'code' => $error->code,
                        'message' => $error->message,
                        'raw_row' => json_encode((object) $error->rawRow),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk));
                }

                $import->update([
                    'total_rows' => $analysis->totalRows,
                    'valid_rows' => $analysis->validRows(),
                    'invalid_rows' => $analysis->invalidRows,
                    'inserted_rows' => $analysis->count(ImportRowResult::New),
                    'corrected_rows' => $analysis->count(ImportRowResult::Corrected),
                    'unchanged_rows' => $analysis->count(ImportRowResult::Unchanged),
                    'min_date' => $analysis->minDate()?->format('Y-m-d'),
                    'max_date' => $analysis->maxDate()?->format('Y-m-d'),
                    'status' => ImportStatus::Validated,
                ]);
            });
        } catch (Throwable $e) {
            // Never log file content, cell values or paths: only the id and the exception class.
            Log::error('Import validation failed', ['import_id' => $import->id, 'exception' => $e::class]);

            $import->update([
                'status' => ImportStatus::Failed,
                'error_message' => 'The file could not be validated. Please upload it again.',
            ]);
            ImportFailed::dispatch($import->id, $import->department_id, $user->id);
        }

        return $import->refresh();
    }

    /**
     * The Owner uploads into the company's single department, a Manager into their own.
     */
    private function targetDepartment(User $user): Department
    {
        $department = $user->isOwner()
            ? Department::query()->where('company_id', $user->company_id)->orderBy('id')->first()
            : Department::query()->find($user->department_id);

        if ($department === null) {
            throw new ImportRejectedException('no_department', 'No department is available to import into.');
        }

        return $department;
    }

    /**
     * The client file name is for display only; it is never used in a storage path.
     */
    private function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return Str::limit($name !== '' ? $name : 'upload.csv', 200, '');
    }
}
