<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { StatusTone, TaskStatusValue } from '@/types';

const props = defineProps<{ status: TaskStatusValue; label: string }>();

const tones: Record<TaskStatusValue, StatusTone> = {
    pending: 'neutral',
    in_progress: 'info',
    blocked: 'warning',
    completed: 'success',
    cancelled: 'neutral',
};

const tone = computed(() => tones[props.status]);
</script>

<template>
    <StatusBadge :label="label" :tone="tone" />
</template>
