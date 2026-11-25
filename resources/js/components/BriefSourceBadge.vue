<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { BriefStatusValue, StatusTone } from '@/types';

const props = defineProps<{
    status: BriefStatusValue;
    source: 'ai' | 'fallback' | null;
}>();

const view = computed<{ label: string; tone: StatusTone }>(() => {
    if (props.status === 'failed') {
        return { label: 'Failed', tone: 'danger' };
    }

    if (props.status !== 'completed') {
        return {
            label: props.status === 'queued' ? 'Queued' : 'Generating',
            tone: 'info',
        };
    }

    return props.source === 'ai'
        ? { label: 'AI generated', tone: 'success' }
        : { label: 'Generated from stored data', tone: 'neutral' };
});
</script>

<template>
    <StatusBadge :label="view.label" :tone="view.tone" />
</template>
