<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { MeetingStatusValue, StatusTone } from '@/types';

const props = defineProps<{ status: MeetingStatusValue; label: string }>();

const tones: Record<MeetingStatusValue, StatusTone> = {
    draft: 'neutral',
    uploaded: 'info',
    transcribing: 'info',
    transcribed: 'info',
    extracting: 'info',
    ready_for_review: 'success',
    failed: 'danger',
};

const tone = computed(() => tones[props.status]);
</script>

<template>
    <StatusBadge :label="label" :tone="tone" />
</template>
