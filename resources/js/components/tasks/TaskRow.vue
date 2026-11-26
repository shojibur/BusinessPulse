<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
import { computed } from 'vue';
import AssigneeAvatar from '@/components/tasks/AssigneeAvatar.vue';
import OverdueBadge from '@/components/tasks/OverdueBadge.vue';
import StatusActions from '@/components/tasks/StatusActions.vue';
import TaskStatusBadge from '@/components/tasks/TaskStatusBadge.vue';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import type { TaskRowData } from '@/types';

const props = defineProps<{ task: TaskRowData; showActions?: boolean }>();

// A coloured edge: overdue is red, finished work is green, everything else neutral.
const edge = computed(() =>
    props.task.daysOverdue !== null && props.task.daysOverdue > 0
        ? 'border-l-severity-high'
        : props.task.status === 'completed'
          ? 'border-l-success'
          : 'border-l-slate-300',
);
</script>

<template>
    <Card class="gap-3 border-l-4 p-4" :class="edge">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <Link
                :href="task.url"
                class="touch-target text-sm/5 font-semibold text-foreground hover:text-primary-text hover:underline"
                >{{ task.title }}</Link
            >
            <div class="flex flex-wrap items-center gap-2">
                <OverdueBadge :days="task.daysOverdue" />
                <TaskStatusBadge
                    :status="task.status"
                    :label="task.statusLabel"
                />
            </div>
        </div>
        <p
            class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
        >
            <span class="inline-flex items-center gap-1.5">
                <CalendarDays class="size-3.5" aria-hidden="true" />
                Due {{ formatDate(task.dueDate) }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <AssigneeAvatar :name="task.assignee.name" size="sm" />
                {{ task.assignee.name }}
            </span>
        </p>
        <StatusActions
            v-if="showActions"
            :task-id="task.id"
            :current="task.status"
            :transitions="task.allowedTransitions"
        />
    </Card>
</template>
