<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row-level validation problem. Reachable only through the authorization of the parent Import.
 *
 * @property int $id
 * @property int $import_id
 * @property int $row_number
 * @property string|null $column
 * @property string $code
 * @property string $message
 * @property array<string, string> $raw_row
 */
class ImportError extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['raw_row' => 'array'];
    }

    /** @return BelongsTo<Import, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
