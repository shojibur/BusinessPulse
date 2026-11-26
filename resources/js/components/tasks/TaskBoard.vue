<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    Check,
    CirclePause,
    CirclePlay,
    Circle,
    TriangleAlert,
} from '@lucide/vue';
import type { Component } from 'vue';
import AssigneeAvatar from '@/components/tasks/AssigneeAvatar.vue';
import { formatDate } from '@/lib/format';
import type { TaskBoardData, TaskStatusValue } from '@/types';

defineProps<{
    board: TaskBoardData;
    /** Builds the link that opens the table filtered to one status. */
    listUrl: (status: TaskStatusValue) => string;
}>();

// Each column has its own tint, so the state of the work reads at a glance.
const tones: Record<
    string,
    { band: string; chip: string; icon: Component; card: string }
> = {
    pending: {
        band: 'bg-muted text-foreground',
        chip: 'bg-card text-muted-foreground',
        icon: Circle,
        card: 'bg-muted/30',
    },
    in_progress: {
        band: 'bg-info-bg text-info',
        chip: 'bg-card text-info',
        icon: CirclePlay,
        card: 'bg-info-bg/40',
    },
    blocked: {
        band: 'bg-severity-high-bg text-severity-high',
        chip: 'bg-card text-severity-high',
        icon: CirclePause,
        card: 'bg-severity-high-bg/40',
    },
    completed: {
        band: 'bg-success-bg text-success',
        chip: 'bg-card text-success',
        icon: Check,
        card: 'bg-success-bg/40',
    },
};
</script>

<template>
    <div
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
        role="list"
        aria-label="Task board"
    >
        <section
            v-for="column in board.columns"
            :key="column.status"
            class="flex min-w-0 flex-col overflow-hidden rounded-xl border"
            :class="tones[column.status].card"
            role="listitem"
        >
            <header
                class="flex items-center justify-between gap-2 px-4 py-3"
                :class="tones[column.status].band"
            >
                <h3 class="flex items-center gap-2 text-sm font-semibold">
                    <component
                        :is="tones[column.status].icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ column.label }}
                </h3>
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums"
                    :class="tones[column.status].chip"
                    >{{ column.count }}</span
                >
            </header>

            <ul class="flex-1 space-y-3 p-3">
                <li
                    v-if="column.items.length === 0"
                    class="rounded-lg border border-dashed bg-card/60 px-3 py-6 text-center text-xs text-muted-foreground"
                >
                    No tasks
                </li>
                <li
                    v-for="task in column.items"
                    :key="task.id"
                    class="space-y-2.5 rounded-lg border bg-card p-3 shadow-xs"
                >
                    <Link
                        :href="task.url"
                        class="block text-sm/5 font-semibold text-foreground hover:text-primary-text hover:underline"
                        >{{ task.title }}</Link
                    >
                    <p
                        v-if="task.issue"
                        class="flex items-center gap-1.5 truncate rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground"
                        :title="task.issue.title"
                    >
                        <TriangleAlert
                            class="size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="truncate">{{ task.issue.title }}</span>
                    </p>
                    <div class="flex items-center gap-2 text-xs">
                        <AssigneeAvatar :name="task.assignee.name" size="sm" />
                        <span class="truncate font-medium">{{
                            task.assignee.name
                        }}</span>
                    </div>
                    <p
                        class="flex items-center gap-1.5 text-xs"
                        :class="
                            task.daysOverdue !== null && task.daysOverdue > 0
                                ? 'font-medium text-severity-high'
                                : 'text-muted-foreground'
                        "
                    >
                        <CalendarDays class="size-3.5" aria-hidden="true" />
                        Due {{ formatDate(task.dueDate) }}
                        <span
                            v-if="
                                task.daysOverdue !== null &&
                                task.daysOverdue > 0
                            "
                            >(Overdue)</span
                        >
                    </p>
                </li>
            </ul>

            <footer v-if="column.count > 0" class="px-4 pb-3 text-center">
                <Link
                    :href="listUrl(column.status)"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary-text hover:underline"
                >
                    View {{ column.count }}
                    {{ column.label.toLowerCase() }}
                    <ArrowRight class="size-3.5" aria-hidden="true" />
                </Link>
            </footer>
        </section>
    </div>
</template>
