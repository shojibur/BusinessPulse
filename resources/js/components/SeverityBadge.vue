<script setup lang="ts">
import { OctagonAlert, TriangleAlert, Info } from '@lucide/vue';
import { computed } from 'vue';
import type { Severity, SeverityBadgeProps } from '@/types';

const props = defineProps<SeverityBadgeProps>();

const config: Record<
    Severity,
    { label: string; icon: typeof Info; classes: string }
> = {
    high: {
        label: 'High',
        icon: OctagonAlert,
        classes: 'bg-severity-high-bg text-severity-high',
    },
    medium: {
        label: 'Medium',
        icon: TriangleAlert,
        classes: 'bg-severity-medium-bg text-severity-medium',
    },
    low: {
        label: 'Low',
        icon: Info,
        classes: 'bg-severity-low-bg text-severity-low',
    },
};

const current = computed(() => config[props.severity]);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs/4 font-medium whitespace-nowrap"
        :class="current.classes"
    >
        <component :is="current.icon" class="size-3.5" aria-hidden="true" />
        {{ current.label }}
    </span>
</template>
