<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { ListChecks } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import OverdueBadge from '@/components/tasks/OverdueBadge.vue';
import TaskStatusBadge from '@/components/tasks/TaskStatusBadge.vue';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import type { IssueTasks, StatusTone } from '@/types';

const props = defineProps<{ tasks: IssueTasks }>();

const chips = computed<{ label: string; count: number; tone: StatusTone }[]>(
    () => [
        {
            label: 'pending',
            count: props.tasks.counts.pending,
            tone: 'neutral',
        },
        {
            label: 'in progress',
            count: props.tasks.counts.in_progress,
            tone: 'info',
        },
        {
            label: 'blocked',
            count: props.tasks.counts.blocked,
            tone: 'warning',
        },
        {
            label: 'completed',
            count: props.tasks.counts.completed,
            tone: 'success',
        },
        {
            label: 'cancelled',
            count: props.tasks.counts.cancelled,
            tone: 'neutral',
        },
    ],
);
</script>

<template>
    <section class="space-y-3" aria-labelledby="related-tasks-heading">
        <SectionHeader
            id="related-tasks-heading"
            title="Tasks"
            description="Completing every task moves this issue to monitoring. It does not resolve it."
            :icon="ListChecks"
        />
        <div class="flex flex-wrap gap-2" role="status">
            <StatusBadge
                v-for="chip in chips"
                :key="chip.label"
                :label="`${chip.count} ${chip.label}`"
                :tone="chip.count > 0 ? chip.tone : 'neutral'"
            />
        </div>
        <Card class="gap-0 p-0">
            <div
                v-if="tasks.items.length === 0"
                class="flex flex-col items-center gap-1 px-6 py-8 text-center"
            >
                <ListChecks
                    class="size-6 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="text-sm text-muted-foreground">
                    No tasks are linked to this issue yet.
                </p>
            </div>
            <ul v-else class="divide-y">
                <li
                    v-for="task in tasks.items"
                    :key="task.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5 text-sm"
                >
                    <Link
                        :href="task.url"
                        class="touch-target font-medium text-primary-text hover:underline"
                        >{{ task.title }}</Link
                    >
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-muted-foreground"
                            >{{ task.assigneeName }} · due
                            {{ formatDate(task.dueDate) }}</span
                        >
                        <OverdueBadge :days="task.isOverdue ? 1 : null" />
                        <TaskStatusBadge
                            :status="task.status"
                            :label="task.statusLabel"
                        />
                    </span>
                </li>
            </ul>
        </Card>
    </section>
</template>
