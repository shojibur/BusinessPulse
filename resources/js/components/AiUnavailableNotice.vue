<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
</script>

<template>
    <Alert role="status">
        <TriangleAlert aria-hidden="true" />
        <AlertTitle>AI is not set up</AlertTitle>
        <AlertDescription>
            No AI provider is configured on this site, so briefs are a plain
            summary of the stored figures, with no written commentary. Ask your
            platform administrator to add an AI provider in the platform
            settings.
        </AlertDescription>
    </Alert>
</template>
