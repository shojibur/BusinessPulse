<script setup lang="ts">
defineProps<{ labels: string[] }>();
</script>

<template>
    <span v-if="labels.length" class="tabular-nums">{{
        labels.join(' · ')
    }}</span>
    <span v-else class="text-muted-foreground">No change figure</span>
</template>
