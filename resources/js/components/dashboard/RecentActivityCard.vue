<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Card } from '@/components/ui/card';
import type { DashboardActivityEntry } from './types';

defineProps<{ entries: DashboardActivityEntry[] }>();
</script>

<template>
    <Card class="gap-4 p-5">
        <SectionHeader
            title="Recent activity"
            description="Key events in your business."
            :icon="Clock"
        />
        <EmptyState
            v-if="entries.length === 0"
            :icon="Clock"
            message="Nothing has happened yet. Activity appears here as work is done."
        />
        <ol v-else class="relative ms-1.5 space-y-4 border-s border-border">
            <li v-for="entry in entries" :key="entry.id" class="ms-5">
                <span
                    class="absolute -start-[6px] mt-1.5 size-3 rounded-full bg-primary ring-4 ring-card"
                    aria-hidden="true"
                />
                <p class="text-sm/5 font-medium text-foreground">
                    {{ entry.label }}
                </p>
                <p class="text-xs/4 text-muted-foreground">
                    {{ entry.by }} · {{ entry.atLabel }}
                    <template v-if="entry.title">
                        ·
                        <Link
                            v-if="entry.url"
                            :href="entry.url"
                            class="text-primary-text hover:underline"
                            >{{ entry.title }}</Link
                        >
                        <template v-else>{{ entry.title }}</template>
                    </template>
                </p>
            </li>
        </ol>
    </Card>
</template>
