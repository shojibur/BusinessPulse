<script setup lang="ts">
import {
    Ban,
    CircleCheck,
    ClipboardCheck,
    FileUp,
    LoaderCircle,
    OctagonX,
    TriangleAlert,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import type { ImportStatusValue } from '@/types';

const props = defineProps<{ status: ImportStatusValue; label: string }>();

// Status is shown with an icon AND a text label, never by colour alone.
const config: Record<ImportStatusValue, { icon: Component; classes: string }> =
    {
        uploaded: { icon: FileUp, classes: 'bg-info-bg text-info' },
        validated: { icon: ClipboardCheck, classes: 'bg-info-bg text-info' },
        processing: {
            icon: LoaderCircle,
            classes: 'bg-info-bg text-info',
        },
        completed: { icon: CircleCheck, classes: 'bg-success-bg text-success' },
        completed_with_errors: {
            icon: TriangleAlert,
            classes: 'bg-severity-medium-bg text-severity-medium',
        },
        failed: {
            icon: OctagonX,
            classes: 'bg-severity-high-bg text-severity-high',
        },
        cancelled: {
            icon: Ban,
            classes: 'bg-severity-low-bg text-severity-low',
        },
    };

const current = computed(() => config[props.status]);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs/4 font-medium whitespace-nowrap"
        :class="current.classes"
    >
        <component
            :is="current.icon"
            class="size-3.5"
            :class="status === 'processing' && 'animate-spin'"
            aria-hidden="true"
        />
        {{ label }}
    </span>
</template>
