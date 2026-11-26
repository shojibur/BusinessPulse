<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import { Route } from '@lucide/vue';
import { Deferred } from '@inertiajs/vue3';
import TraceTimeline from '@/components/activity/TraceTimeline.vue';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { TracePanelData } from '@/types';

defineProps<{ trace?: TracePanelData | null }>();
</script>

<template>
    <section class="space-y-3" aria-labelledby="trace-heading">
        <SectionHeader
            id="trace-heading"
            title="Activity trace"
            description="From imported data to the outcome."
            :icon="Route"
        />
        <Deferred data="trace">
            <template #fallback><Skeleton class="h-24 w-full" /></template>
            <Card class="p-5"
                ><TraceTimeline v-if="trace" :trace="trace"
            /></Card>
        </Deferred>
    </section>
</template>
