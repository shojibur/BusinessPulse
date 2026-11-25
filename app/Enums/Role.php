<?php

namespace App\Enums;

/**
 * The three fixed roles (docs/business-workflows.md D23). There are no custom roles
 * and no per-user permissions.
 *
 * - Owner: company-wide access to all data and the only credential administrator.
 * - Manager: access limited to the data of the own department; read-only staff list.
 * - Staff: access limited to own tasks (S07 onward).
 *
 * Out-of-scope records return 404; a role that may not perform an action returns 403.
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
