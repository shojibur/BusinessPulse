<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StatusBadge from '@/components/StatusBadge.vue';
import type { TracePanelData } from '@/types';

defineProps<{ trace: TracePanelData }>();
</script>

<template>
    <div class="space-y-4">
        <ol class="space-y-4">
            <li v-for="stage in trace.stages" :key="stage.stage">
                <h3 class="text-sm font-semibold">{{ stage.stageLabel }}</h3>
                <p v-if="stage.empty" class="text-sm text-muted-foreground">
                    No activity yet
                </p>
                <ul v-else class="mt-1 space-y-2 border-l pl-3">
                    <li
                        v-for="entry in stage.entries"
                        :key="entry.key"
                        class="text-sm"
                    >
                        <p>
                            <span class="font-medium">{{ entry.label }}</span>
                            <StatusBadge
                                v-if="entry.phaseLabel"
                                :label="entry.phaseLabel"
                                tone="neutral"
                                class="ml-2"
                            />
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ entry.atLabel }} · {{ entry.userName }}
                            <template
                                v-if="entry.subject.title || entry.subject.url"
                            >
                                ·
                                <Link
                                    v-if="entry.subject.url"
                                    :href="entry.subject.url"
                                    class="text-primary-text underline"
                                    >{{
                                        entry.subject.title ??
                                        `${entry.subject.type} #${entry.subject.id}`
                                    }}</Link
                                >
                                <template v-else>{{
                                    entry.subject.title
                                }}</template>
                            </template>
                        </p>
                    </li>
                </ul>
            </li>
        </ol>
        <p v-if="trace.truncated" class="text-xs text-muted-foreground">
            Showing the first 500 entries.
        </p>
    </div>
</template>
