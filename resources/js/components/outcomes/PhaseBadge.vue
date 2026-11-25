<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';

defineProps<{ label: string; usedForVerification: boolean }>();
</script>

<template>
    <StatusBadge
        :label="label"
        :tone="usedForVerification ? 'info' : 'neutral'"
    />
</template>
