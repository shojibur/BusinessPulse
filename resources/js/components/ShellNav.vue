<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Building2,
    Circle,
    FileText,
    LayoutDashboard,
    ListChecks,
    Mic,
    Settings,
    Sparkles,
    TrendingUp,
    TriangleAlert,
    Upload,
} from '@lucide/vue';
import type { Component } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { NavItem } from '@/types';

withDefaults(
    defineProps<{
        items: NavItem[];
        /** Icon-only rail: labels are hidden (kept for screen readers) and shown in a tooltip. */
        collapsed?: boolean;
    }>(),
    { collapsed: false },
);

// Lucide icons named by config/navigation.php.
const icons: Record<string, Component> = {
    LayoutDashboard,
    TriangleAlert,
    Upload,
    FileText,
    Mic,
    ListChecks,
    TrendingUp,
    Settings,
    Building2,
    Sparkles,
};
</script>

<template>
    <ul class="flex flex-col gap-1">
        <li v-for="item in items" :key="item.href">
            <Tooltip :disabled="!collapsed">
                <TooltipTrigger as-child>
                    <Link
                        :href="item.href"
                        :aria-current="item.active ? 'page' : undefined"
                        class="flex h-10 items-center gap-3 rounded-md text-sm font-medium text-sidebar-foreground outline-none hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                        :class="[
                            collapsed ? 'justify-center px-0' : 'px-3',
                            item.active &&
                                'bg-sidebar-accent text-sidebar-accent-foreground',
                        ]"
                    >
                        <component
                            :is="icons[item.icon] ?? Circle"
                            class="size-5 shrink-0"
                            :class="item.active && 'text-sidebar-ring'"
                            aria-hidden="true"
                        />
                        <span :class="collapsed && 'sr-only'">
                            {{ item.label }}
                        </span>
                    </Link>
                </TooltipTrigger>
                <TooltipContent side="right">
                    {{ item.label }}
                </TooltipContent>
            </Tooltip>
        </li>
    </ul>
</template>
