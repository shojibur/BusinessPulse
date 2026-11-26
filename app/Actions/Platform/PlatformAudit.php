<?php

namespace App\Actions\Platform;

use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;

/**
 * Appends one row to the platform audit log. Callers pass only non-secret properties: never a password, token or
 * the content of any company's business data.
 */
final class PlatformAudit
{
    /** @param  array<string, mixed>  $properties */
    public function record(?PlatformAdmin $admin, string $action, ?int $companyId = null, array $properties = []): void
    {
        PlatformAuditLog::query()->create([
            'platform_admin_id' => $admin?->id,
            'action' => $action,
            'company_id' => $companyId,
            'properties' => $properties === [] ? null : $properties,
        ]);
    }
}
