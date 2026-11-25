<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { IssueStatusValue, StatusTone } from '@/types';

const props = defineProps<{ status: IssueStatusValue; label: string }>();

const tones: Record<IssueStatusValue, StatusTone> = {
    open: 'danger',
    acknowledged: 'warning',
    action_in_progress: 'info',
    monitoring: 'info',
    resolved: 'success',
};

const tone = computed(() => tones[props.status]);
</script>

<template>
    <StatusBadge :label="label" :tone="tone" />
</template>
