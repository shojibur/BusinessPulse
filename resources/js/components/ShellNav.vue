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
    <ul class="flex flex-col gap-0.5">
        <template v-for="(item, index) in items" :key="item.href">
            <li
                v-if="item.group && item.group !== items[index - 1]?.group"
                :class="
                    collapsed
                        ? 'my-2 border-t border-sidebar-border'
                        : 'px-3 pt-5 pb-1.5'
                "
            >
                <span
                    class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    :class="collapsed && 'sr-only'"
                    >{{ item.group }}</span
                >
            </li>
            <li>
                <Tooltip :disabled="!collapsed">
                    <TooltipTrigger as-child>
                        <Link
                            :href="item.href"
                            :aria-current="item.active ? 'page' : undefined"
                            class="relative flex h-10 items-center gap-3 rounded-lg text-sm font-medium text-sidebar-foreground outline-none transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                            :class="[
                                collapsed ? 'justify-center px-0' : 'px-3',
                                item.active &&
                                    'bg-sidebar-accent text-sidebar-accent-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                            ]"
                        >
                            <component
                                :is="icons[item.icon] ?? Circle"
                                class="size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <span :class="collapsed ? 'sr-only' : 'flex-1'">
                                {{ item.label }}
                            </span>
                            <span
                                v-if="item.badge"
                                class="tabular-nums"
                                :class="
                                    collapsed
                                        ? 'absolute top-1 right-1.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-4 font-semibold text-primary-foreground'
                                        : 'rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-foreground'
                                "
                                :aria-label="`${item.badge} open`"
                                >{{ item.badge }}</span
                            >
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent side="right">
                        {{ item.label }}
                    </TooltipContent>
                </Tooltip>
            </li>
        </template>
    </ul>
</template>
