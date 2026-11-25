<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';

defineProps<{ days: number | null }>();
</script>

<template>
    <StatusBadge
        v-if="days !== null"
        :label="days === 1 ? '1 day overdue' : `${days} days overdue`"
        tone="danger"
    />
</template>
