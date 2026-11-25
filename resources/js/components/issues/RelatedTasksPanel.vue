<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import OverdueBadge from '@/components/tasks/OverdueBadge.vue';
import TaskStatusBadge from '@/components/tasks/TaskStatusBadge.vue';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import type { IssueTasks } from '@/types';

defineProps<{ tasks: IssueTasks }>();
</script>

<template>
    <section class="space-y-3" aria-labelledby="related-tasks-heading">
        <h2 id="related-tasks-heading" class="text-lg/7 font-semibold">
            Tasks
        </h2>
        <p class="text-sm text-muted-foreground">
            Completing tasks moves this issue to monitoring; it does not resolve
            it, and it does not show that the tasks improved results.
        </p>
        <p class="text-sm tabular-nums" role="status">
            {{ tasks.counts.pending }} pending ·
            {{ tasks.counts.in_progress }} in progress ·
            {{ tasks.counts.blocked }} blocked ·
            {{ tasks.counts.completed }} completed ·
            {{ tasks.counts.cancelled }} cancelled
        </p>
        <Card class="gap-0 p-0">
            <p
                v-if="tasks.items.length === 0"
                class="px-6 py-4 text-sm text-muted-foreground"
            >
                No tasks are linked to this issue yet.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="task in tasks.items"
                    :key="task.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 text-sm"
                >
                    <Link
                        :href="task.url"
                        class="touch-target font-medium text-primary-text hover:underline"
                        >{{ task.title }}</Link
                    >
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-muted-foreground"
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
