<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import OverdueBadge from '@/components/tasks/OverdueBadge.vue';
import StatusActions from '@/components/tasks/StatusActions.vue';
import TaskStatusBadge from '@/components/tasks/TaskStatusBadge.vue';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import type { TaskRowData } from '@/types';

defineProps<{ task: TaskRowData; showActions?: boolean }>();
</script>

<template>
    <Card class="gap-2 p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <Link
                :href="task.url"
                class="touch-target font-medium text-primary-text hover:underline"
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
        <p class="text-sm text-muted-foreground">
            Due {{ formatDate(task.dueDate) }} · {{ task.assignee.name }}
        </p>
        <StatusActions
            v-if="showActions"
            :task-id="task.id"
            :current="task.status"
            :transitions="task.allowedTransitions"
        />
    </Card>
</template>
