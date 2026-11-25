<?php

/*
|--------------------------------------------------------------------------
| Primary Navigation
|--------------------------------------------------------------------------
|
| The single source of the primary navigation, shared with the frontend through
| the Inertia `navigation` prop. S01 applies NO role filtering: every visitor
| sees all eight items. S02 replaces this list with role-based visibility
| filtering, and policies (not navigation visibility) enforce authorization.
|
| icon: Lucide icon name (resolved by the AppShell). active: route name pattern.
|
*/

return [
    ['label' => 'Today', 'route' => 'today', 'icon' => 'LayoutDashboard', 'active' => 'today'],
    ['label' => 'Issues', 'route' => 'issues.index', 'icon' => 'TriangleAlert', 'active' => 'issues.*'],
    ['label' => 'Data Imports', 'route' => 'imports.index', 'icon' => 'Upload', 'active' => 'imports.*'],
    ['label' => 'Briefs', 'route' => 'briefs.index', 'icon' => 'FileText', 'active' => 'briefs.*'],
    ['label' => 'Meetings', 'route' => 'meetings.index', 'icon' => 'Mic', 'active' => 'meetings.*'],
    ['label' => 'Tasks', 'route' => 'tasks.index', 'icon' => 'ListChecks', 'active' => 'tasks.*'],
    ['label' => 'Outcomes', 'route' => 'outcomes.index', 'icon' => 'TrendingUp', 'active' => 'outcomes.*'],
    ['label' => 'Settings', 'route' => 'settings.index', 'icon' => 'Settings', 'active' => 'settings.*'],
];
