<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * The table has no company_id column, so the ScopedToDepartment trait does not apply. The Owner sees
     * the records of departments of the own company; others only their own department; inactive users and
     * users without a department see nothing.
     *
     * @param  Builder<SalesRecord>  $query
     * @return Builder<SalesRecord>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isOwner()) {
            return $query->whereIn(
                $query->qualifyColumn('department_id'),
                Department::query()->where('company_id', $user->company_id)->select('id'),
            );
        }

        if ($user->department_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->qualifyColumn('department_id'), $user->department_id);
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
