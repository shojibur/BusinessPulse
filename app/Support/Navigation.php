<?php

namespace App\Support;

use Illuminate\Http\Request;

class Navigation
{
    /**
     * The navigation items for the shell, with the active item marked.
     *
     * S01 applies no role filtering; S02 adds role-based visibility here.
     *
     * @return list<array{label: string, href: string, icon: string, active: bool}>
     */
    public static function items(Request $request): array
    {
        return array_map(fn (array $item): array => [
            'label' => $item['label'],
            'href' => route($item['route'], absolute: false),
            'icon' => $item['icon'],
            'active' => $request->routeIs($item['active']),
        ], config('navigation'));
    }
}
