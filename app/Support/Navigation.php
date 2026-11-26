<?php

namespace App\Support;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Navigation items for the shell, filtered by role.
 *
 * Navigation visibility is DISPLAY ONLY. Access is enforced server-side: the `section.access`
 * middleware returns 403 for sections a role does not see, and policies authorize every record.
 */
class Navigation
{
    private const OPEN_TASK_STATUSES = ['pending', 'in_progress', 'blocked'];

    /**
     * @return list<array{label: string, href: string, icon: string, active: bool, group: string|null, badge: int|null}>
     */
    public static function items(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active) {
            return [];
        }

        $items = [];

        foreach (config('navigation') as $item) {
            if (! self::roleAllowed($user, $item)) {
                continue;
            }

            $items[] = [
                'label' => $item['label'],
                'href' => route($item['route'], absolute: false),
                'icon' => $item['icon'],
                'active' => $request->routeIs($item['active']),
                'group' => $item['group'] ?? null,
                'badge' => self::badge($user, $item['route']),
            ];
        }

        return $items;
    }

    /**
     * The count shown next to a section: open issues and open tasks the user can see. Null shows nothing.
     */
    private static function badge(User $user, string $route): ?int
    {
        $count = match ($route) {
            'issues.index' => Issue::query()->visibleTo($user)->where('status', '!=', IssueStatus::Resolved->value)->count(),
            'tasks.index' => Task::query()->visibleTo($user)->whereIn('status', self::OPEN_TASK_STATUSES)->count(),
            'tasks.mine' => Task::query()->where('assignee_id', $user->id)->whereIn('status', self::OPEN_TASK_STATUSES)->count(),
            default => 0,
        };

        return $count > 0 ? $count : null;
    }

    /**
     * True unless the current route belongs to a navigation section the user's role does not see.
     */
    public static function canAccessCurrentSection(Request $request, User $user): bool
    {
        foreach (config('navigation') as $item) {
            if ($request->routeIs($item['active'])) {
                return self::roleAllowed($user, $item);
            }
        }

        return true;
    }

    /**
     * @param  array{roles: list<string>}  $item
     */
    private static function roleAllowed(User $user, array $item): bool
    {
        return in_array($user->role->value, $item['roles'], true);
    }
}
