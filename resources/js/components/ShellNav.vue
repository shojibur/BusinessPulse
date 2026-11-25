<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Circle,
    FileText,
    LayoutDashboard,
    ListChecks,
    Mic,
    Settings,
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
        /** `responsive` collapses to an icon rail at 1280px and below; `full` always shows labels. */
        mode?: 'responsive' | 'full';
    }>(),
    { mode: 'responsive' },
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
};
</script>

<template>
    <ul class="flex flex-col gap-1">
        <li v-for="item in items" :key="item.href">
            <Tooltip :disabled="mode === 'full'">
                <TooltipTrigger as-child>
                    <Link
                        :href="item.href"
                        :aria-current="item.active ? 'page' : undefined"
                        class="flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium text-sidebar-foreground outline-none hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                        :class="[
                            item.active &&
                                'bg-sidebar-accent text-sidebar-accent-foreground',
                            // Icon rail is the base; the full sidebar (above 1280px) restores labels.
                            mode === 'responsive' &&
                                'justify-center px-0 min-[1281px]:justify-start min-[1281px]:px-3',
                        ]"
                    >
                        <component
                            :is="icons[item.icon] ?? Circle"
                            class="size-5 shrink-0"
                            :class="item.active && 'text-sidebar-ring'"
                            aria-hidden="true"
                        />
                        <span
                            :class="
                                mode === 'responsive' &&
                                'sr-only min-[1281px]:not-sr-only'
                            "
                        >
                            {{ item.label }}
                        </span>
                    </Link>
                </TooltipTrigger>
                <TooltipContent side="right" class="min-[1281px]:hidden">
                    {{ item.label }}
                </TooltipContent>
            </Tooltip>
        </li>
    </ul>
</template>
