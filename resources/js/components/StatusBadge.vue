<script setup lang="ts">
import { computed } from 'vue';
import type { StatusBadgeProps, StatusTone } from '@/types';

const props = defineProps<StatusBadgeProps>();

const toneClasses: Record<StatusTone, string> = {
    neutral: 'bg-severity-low-bg text-severity-low',
    info: 'bg-info-bg text-info',
    success: 'bg-success-bg text-success',
    warning: 'bg-severity-medium-bg text-severity-medium',
    danger: 'bg-severity-high-bg text-severity-high',
};

const classes = computed(() => toneClasses[props.tone]);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs/4 font-medium whitespace-nowrap"
        :class="classes"
    >
        {{ label }}
    </span>
</template>
