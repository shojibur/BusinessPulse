<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One branch-day of sales. History is never deleted: a corrected identity keeps its old row with
 * is_active false and superseded_by_import_id set. Money values are numeric(14,2) strings, never floats.
 *
 * @property int $id
 * @property int $department_id
 * @property int $import_id
 * @property string $branch_code
 * @property Carbon $sales_date
 * @property string $revenue
 * @property string $target_revenue
 * @property int|null $order_count
 * @property bool $is_active
 * @property int|null $superseded_by_import_id
 */
class SalesRecord extends Model
{
    use ScopedThroughDepartment;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sales_date' => 'date:Y-m-d',
            'revenue' => 'decimal:2',
            'target_revenue' => 'decimal:2',
            'is_active' => 'boolean',
            'superseded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Import, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /** @return BelongsTo<Import, $this> */
    public function supersededByImport(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'superseded_by_import_id');
    }
}
