<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CircleDot,
    Database,
    FileText,
    Mic,
    ListChecks,
    Scale,
    TrendingUp,
    TriangleAlert,
} from '@lucide/vue';
import type { Component } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { TracePanelData } from '@/types';

defineProps<{ trace: TracePanelData }>();

// One icon per stage of the work, from imported data to the outcome.
const icons: Record<string, Component> = {
    business_data: Database,
    rule_evaluation: Scale,
    issue: TriangleAlert,
    brief: FileText,
    meeting: Mic,
    task: ListChecks,
    outcome: TrendingUp,
};
</script>

<template>
    <div class="space-y-5">
        <ol class="space-y-5">
            <li v-for="stage in trace.stages" :key="stage.stage">
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-lg"
                        :class="
                            stage.empty
                                ? 'bg-muted text-muted-foreground'
                                : 'bg-primary/10 text-primary-text'
                        "
                    >
                        <component
                            :is="icons[stage.stage] ?? CircleDot"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                    </span>
                    <h3 class="text-sm font-semibold">
                        {{ stage.stageLabel }}
                    </h3>
                </div>
                <p
                    v-if="stage.empty"
                    class="ms-9 mt-0.5 text-xs text-muted-foreground"
                >
                    No activity yet
                </p>
                <ul
                    v-else
                    class="ms-3.5 mt-2 space-y-3 border-s border-border ps-6"
                >
                    <li
                        v-for="entry in stage.entries"
                        :key="entry.key"
                        class="text-sm"
                    >
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ entry.label }}</span>
                            <StatusBadge
                                v-if="entry.phaseLabel"
                                :label="entry.phaseLabel"
                                tone="neutral"
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
                                    class="text-primary-text underline-offset-2 hover:underline"
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
