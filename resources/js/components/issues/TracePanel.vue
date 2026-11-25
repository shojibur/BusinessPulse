<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import TraceTimeline from '@/components/activity/TraceTimeline.vue';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { TracePanelData } from '@/types';

defineProps<{ trace?: TracePanelData | null }>();
</script>

<template>
    <section class="space-y-3" aria-labelledby="trace-heading">
        <h2 id="trace-heading" class="text-lg/7 font-semibold">Trace</h2>
        <p class="text-sm text-muted-foreground">
            From imported sales data to the outcome, using the records and
            activity that exist. Nothing is added for work done before activity
            was recorded.
        </p>
        <Deferred data="trace">
            <template #fallback><Skeleton class="h-24 w-full" /></template>
            <Card class="p-4"
                ><TraceTimeline v-if="trace" :trace="trace"
            /></Card>
        </Deferred>
    </section>
</template>
