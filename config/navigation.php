<?php

/*
|--------------------------------------------------------------------------
| Primary Navigation
|--------------------------------------------------------------------------
|
| The single source of the primary navigation, shared with the frontend through
| the Inertia `navigation` prop, filtered by the signed-in user's role (S02):
| Owner and Manager see all eight items, Staff sees Today and Tasks. Visibility is
| display only; the `section.access` middleware and policies enforce access.
|
| icon: Lucide icon name (resolved by the AppShell). active: route name pattern.
| roles: the roles that see (and may open) the section.
|
*/

return [
    ['label' => 'Today', 'route' => 'today', 'icon' => 'LayoutDashboard', 'active' => 'today', 'roles' => ['owner', 'manager', 'staff'], 'group' => null],
    ['label' => 'Issues', 'route' => 'issues.index', 'icon' => 'TriangleAlert', 'active' => 'issues.*', 'roles' => ['owner', 'manager'], 'group' => null],
    ['label' => 'Briefs', 'route' => 'briefs.index', 'icon' => 'FileText', 'active' => 'briefs.*', 'roles' => ['owner', 'manager'], 'group' => null],
    ['label' => 'Meetings', 'route' => 'meetings.index', 'icon' => 'Mic', 'active' => 'meetings.*', 'roles' => ['owner', 'manager'], 'group' => null],
    ['label' => 'Tasks', 'route' => 'tasks.index', 'icon' => 'ListChecks', 'active' => 'tasks.index', 'roles' => ['owner', 'manager'], 'group' => null],
    ['label' => 'My Tasks', 'route' => 'tasks.mine', 'icon' => 'CircleCheck', 'active' => 'tasks.mine', 'roles' => ['owner', 'manager', 'staff'], 'group' => null],
    ['label' => 'Outcomes', 'route' => 'outcomes.index', 'icon' => 'TrendingUp', 'active' => 'outcomes.*', 'roles' => ['owner', 'manager'], 'group' => null],
    ['label' => 'Data Imports', 'route' => 'imports.index', 'icon' => 'Upload', 'active' => 'imports.*', 'roles' => ['owner', 'manager'], 'group' => 'Company'],
    ['label' => 'Settings', 'route' => 'settings.index', 'icon' => 'Settings', 'active' => 'settings.*', 'roles' => ['owner', 'manager'], 'group' => 'Company'],
];
