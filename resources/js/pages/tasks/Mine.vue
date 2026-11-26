<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleAlert, ListChecks } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import TaskRow from '@/components/tasks/TaskRow.vue';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { mine } from '@/routes/tasks';
import type { TaskRowData, TaskSummary } from '@/types';
import { ref } from 'vue';

const props = defineProps<{
    sections: {
        overdue: TaskRowData[];
        open: TaskRowData[];
        completed: TaskRowData[];
        cancelled: TaskRowData[] | null;
    };
    filters: { status: string | null; showCancelled: boolean };
    summary: TaskSummary;
    businessToday: string;
}>();

const status = ref(props.filters.status ?? '');
const showCancelled = ref(props.filters.showCancelled);

function apply(): void {
    router.get(
        mine().url,
        {
            ...(status.value ? { status: status.value } : {}),
            ...(showCancelled.value ? { showCancelled: 1 } : {}),
        },
        { preserveState: true, replace: true },
    );
}

const empty = () =>
    props.sections.overdue.length +
        props.sections.open.length +
        props.sections.completed.length +
        (props.sections.cancelled?.length ?? 0) ===
    0;
</script>

<template>
    <Head title="My tasks" />

    <PageHeader title="My tasks" description="The tasks assigned to you." />

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2" aria-label="Task summary">
            <MetricTile
                label="Open tasks"
                :value="String(summary.open)"
                caption="Assigned to you and not finished"
                :icon="ListChecks"
            />
            <MetricTile
                label="Overdue"
                :value="String(summary.overdue)"
                :caption="
                    summary.overdue > 0
                        ? 'Past their due date'
                        : 'Nothing is late'
                "
                :icon="CircleAlert"
                :tone="summary.overdue > 0 ? 'danger' : 'success'"
            />
        </div>

        <Card class="gap-4 p-5">
            <SectionHeader title="Filter" />
            <div class="flex flex-wrap items-end gap-4">
                <div class="grid gap-1">
                    <label
                        for="mine-status"
                        class="text-xs font-medium text-muted-foreground"
                        >Status</label
                    >
                    <NativeSelect
                        id="mine-status"
                        v-model="status"
                        class="min-w-44"
                        @update:model-value="apply"
                    >
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In progress</option>
                        <option value="blocked">Blocked</option>
                        <option value="completed">Completed</option>
                    </NativeSelect>
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm">
                    <input
                        v-model="showCancelled"
                        type="checkbox"
                        class="size-4"
                        @change="apply"
                    />
                    Show cancelled
                </label>
            </div>
        </Card>

        <Card v-if="empty()"
            ><EmptyState :icon="ListChecks" message="No tasks assigned to you."
        /></Card>

        <template v-else>
            <section
                v-if="sections.overdue.length"
                aria-labelledby="overdue-heading"
                class="space-y-3"
            >
                <SectionHeader id="overdue-heading" title="Overdue" />
                <TaskRow
                    v-for="task in sections.overdue"
                    :key="task.id"
                    :task="task"
                    show-actions
                />
            </section>
            <section
                v-if="sections.open.length"
                aria-labelledby="open-heading"
                class="space-y-3"
            >
                <SectionHeader id="open-heading" title="Open" />
                <TaskRow
                    v-for="task in sections.open"
                    :key="task.id"
                    :task="task"
                    show-actions
                />
            </section>
            <section
                v-if="sections.completed.length"
                aria-labelledby="done-heading"
                class="space-y-3"
            >
                <SectionHeader id="done-heading" title="Completed" />
                <TaskRow
                    v-for="task in sections.completed"
                    :key="task.id"
                    :task="task"
                />
            </section>
            <section
                v-if="sections.cancelled?.length"
                aria-labelledby="cancelled-heading"
                class="space-y-3"
            >
                <SectionHeader id="cancelled-heading" title="Cancelled" />
                <TaskRow
                    v-for="task in sections.cancelled"
                    :key="task.id"
                    :task="task"
                />
            </section>
        </template>
    </div>
</template>
