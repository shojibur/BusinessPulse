<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of a platform action. Properties never hold a password or any secret.
 *
 * @property int $id
 * @property int|null $platform_admin_id
 * @property string $action
 * @property int|null $company_id
 * @property array<string, mixed>|null $properties
 */
class PlatformAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<PlatformAdmin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }
}
