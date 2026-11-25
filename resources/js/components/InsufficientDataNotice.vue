<script setup lang="ts">
import { Info } from '@lucide/vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

defineProps<{ message: string; title?: string }>();
</script>

<template>
    <Alert role="status">
        <Info aria-hidden="true" />
        <AlertTitle>{{
            title ?? 'Not enough data to show this figure'
        }}</AlertTitle>
        <AlertDescription>{{ message }}</AlertDescription>
    </Alert>
</template>
