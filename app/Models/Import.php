<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Concerns\ScopedToDepartment;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One uploaded CSV file and its lifecycle.
 *
 * Counter invariants for validated, completed and completed_with_errors imports:
 * total_rows = valid_rows + invalid_rows and valid_rows = inserted_rows + corrected_rows + unchanged_rows.
 *
 * @property int $id
 * @property int $company_id
 * @property int $department_id
 * @property int $uploaded_by
 * @property string $original_filename
 * @property string $stored_path
 * @property string|null $file_hash
 * @property ImportStatus $status
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property int $inserted_rows
 * @property int $corrected_rows
 * @property int $unchanged_rows
 * @property Carbon|null $min_date
 * @property Carbon|null $max_date
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $completed_at
 * @property string|null $error_message
 */
// The private storage path never leaves the server.
#[Hidden(['stored_path'])]
class Import extends Model
{
    use ScopedToDepartment;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'min_date' => 'date',
            'max_date' => 'date',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<ImportError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    /** @return HasMany<SalesRecord, $this> */
    public function salesRecords(): HasMany
    {
        return $this->hasMany(SalesRecord::class);
    }
}
