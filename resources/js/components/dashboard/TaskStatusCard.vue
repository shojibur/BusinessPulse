<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ListChecks } from '@lucide/vue';
import { computed } from 'vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Card } from '@/components/ui/card';
import { index as tasksIndex } from '@/routes/tasks';
import type { TaskStatusCounts } from './types';

const props = defineProps<{ status: TaskStatusCounts }>();

const rows = computed(() =>
    [
        { key: 'completed', label: 'Completed', color: '#15803d' },
        { key: 'in_progress', label: 'In progress', color: '#0f9488' },
        { key: 'pending', label: 'Pending', color: '#94a3b8' },
        { key: 'blocked', label: 'Blocked', color: '#d97706' },
        { key: 'cancelled', label: 'Cancelled', color: '#cbd5e1' },
    ].map((row) => ({
        ...row,
        count: props.status[row.key as keyof TaskStatusCounts] as number,
        percent: props.status.percent[row.key] ?? 0,
    })),
);

// Donut geometry only: each ring segment is drawn from its share of the stored task counts.
const RADIUS = 52;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const segments = computed(() => {
    let offset = 0;

    return rows.value
        .filter((row) => row.count > 0)
        .map((row) => {
            const length = (row.count / props.status.total) * CIRCUMFERENCE;
            const segment = {
                key: row.key,
                color: row.color,
                dash: `${length} ${CIRCUMFERENCE - length}`,
                offset: -offset,
            };
            offset += length;

            return segment;
        });
});

const summary = computed(() =>
    rows.value
        .filter((row) => row.count > 0)
        .map((row) => `${row.count} ${row.label.toLowerCase()}`)
        .join(', '),
);
</script>

<template>
    <Card class="gap-4 p-5">
        <SectionHeader
            title="Decisions & task status"
            description="Tasks from management meetings."
            :icon="ListChecks"
        >
            <Link
                :href="tasksIndex().url"
                class="text-sm font-medium text-primary-text hover:underline"
                >View tasks</Link
            >
        </SectionHeader>
        <div class="grid items-center gap-5 sm:grid-cols-[140px_minmax(0,1fr)]">
            <div class="relative mx-auto size-[140px]">
                <svg
                    viewBox="0 0 140 140"
                    class="size-full -rotate-90"
                    role="img"
                    :aria-label="
                        status.total === 0
                            ? 'No tasks yet'
                            : `${status.total} tasks: ${summary}`
                    "
                >
                    <circle
                        cx="70"
                        cy="70"
                        :r="RADIUS"
                        fill="none"
                        stroke="#eef2f6"
                        stroke-width="16"
                    />
                    <circle
                        v-for="segment in segments"
                        :key="segment.key"
                        cx="70"
                        cy="70"
                        :r="RADIUS"
                        fill="none"
                        :stroke="segment.color"
                        stroke-width="16"
                        :stroke-dasharray="segment.dash"
                        :stroke-dashoffset="segment.offset"
                    />
                </svg>
                <div
                    class="absolute inset-0 flex flex-col items-center justify-center"
                >
                    <span
                        class="text-2xl/7 font-semibold text-foreground tabular-nums"
                        >{{ status.total }}</span
                    >
                    <span class="text-xs text-muted-foreground">{{
                        status.total === 1 ? 'Task' : 'Tasks'
                    }}</span>
                </div>
            </div>
            <ul class="space-y-2 text-sm">
                <li
                    v-for="row in rows"
                    :key="row.key"
                    class="flex items-center gap-2"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: row.color }"
                        aria-hidden="true"
                    />
                    <span class="flex-1 text-foreground">{{ row.label }}</span>
                    <span class="font-medium tabular-nums">{{
                        row.count
                    }}</span>
                    <span
                        class="w-10 text-right text-xs text-muted-foreground tabular-nums"
                        >{{ row.percent }}%</span
                    >
                </li>
            </ul>
        </div>
    </Card>
</template>
