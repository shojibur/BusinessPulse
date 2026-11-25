<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { OutcomeResultCode, StatusTone } from '@/types';

const props = defineProps<{ result: OutcomeResultCode; label: string }>();

const tones: Record<OutcomeResultCode, StatusTone> = {
    improved: 'success',
    unchanged: 'neutral',
    worsened: 'danger',
    not_evaluable: 'neutral',
};
const tone = computed(() => tones[props.result]);
</script>

<template>
    <StatusBadge :label="label" :tone="tone" />
</template>
