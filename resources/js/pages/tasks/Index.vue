<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleAlert, ListChecks } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import OverdueBadge from '@/components/tasks/OverdueBadge.vue';
import TaskRow from '@/components/tasks/TaskRow.vue';
import TaskStatusBadge from '@/components/tasks/TaskStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/tasks';
import type { Pagination, TaskRowData, TaskSummary } from '@/types';

const props = defineProps<{
    tasks: TaskRowData[];
    pagination: Pagination;
    filters: {
        status: string | null;
        assignee: number | null;
        overdue: boolean;
    };
    options: {
        assignees: { id: number; name: string }[];
        statuses: { value: string; label: string }[];
    };
    summary: TaskSummary;
    businessToday: string;
}>();

const status = ref(props.filters.status ?? '');
const assignee = ref(
    props.filters.assignee === null ? '' : String(props.filters.assignee),
);
const overdue = ref(props.filters.overdue);

function apply(): void {
    router.get(
        index().url,
        {
            ...(status.value ? { status: status.value } : {}),
            ...(assignee.value ? { assignee: assignee.value } : {}),
            ...(overdue.value ? { overdue: 1 } : {}),
        },
        { preserveState: true, replace: true },
    );
}

function clear(): void {
    status.value = '';
    assignee.value = '';
    overdue.value = false;
    apply();
}

const filtered = (): boolean =>
    !!props.filters.status ||
    props.filters.assignee !== null ||
    props.filters.overdue;
</script>

<template>
    <Head title="Tasks" />

    <PageHeader
        title="Tasks"
        description="Approved tasks and their progress. Review proposed tasks from the meeting page."
    >
        <Button variant="outline" as-child
            ><Link :href="'/meetings'">Meetings</Link></Button
        >
    </PageHeader>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2" role="status">
            <MetricTile
                label="Open tasks"
                :value="String(summary.open)"
                caption="Across the people you can see"
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
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1">
                    <label
                        for="task-status"
                        class="text-xs font-medium text-muted-foreground"
                        >Status</label
                    >
                    <NativeSelect
                        id="task-status"
                        v-model="status"
                        class="min-w-44"
                        @update:model-value="apply"
                    >
                        <option value="">All statuses</option>
                        <option value="open">All open</option>
                        <option
                            v-for="option in options.statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </div>
                <div class="grid gap-1">
                    <label
                        for="task-assignee"
                        class="text-xs font-medium text-muted-foreground"
                        >Employee</label
                    >
                    <NativeSelect
                        id="task-assignee"
                        v-model="assignee"
                        class="min-w-48"
                        @update:model-value="apply"
                    >
                        <option value="">Everyone</option>
                        <option
                            v-for="person in options.assignees"
                            :key="person.id"
                            :value="String(person.id)"
                        >
                            {{ person.name }}
                        </option>
                    </NativeSelect>
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm">
                    <input
                        v-model="overdue"
                        type="checkbox"
                        class="size-4"
                        @change="apply"
                    />
                    Overdue only
                </label>
                <Button
                    v-if="filtered()"
                    variant="ghost"
                    size="sm"
                    @click="clear"
                    >Clear filters</Button
                >
            </div>
        </Card>

        <Card v-if="tasks.length === 0">
            <EmptyState
                :icon="ListChecks"
                :message="
                    filtered()
                        ? 'No tasks match the selected filters.'
                        : 'No tasks yet. Approve proposed tasks from a meeting to create them.'
                "
            >
                <Button v-if="filtered()" variant="outline" @click="clear"
                    >Clear filters</Button
                >
            </EmptyState>
        </Card>

        <template v-else>
            <Card class="hidden gap-0 overflow-hidden p-0 md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Task</TableHead>
                            <TableHead>Employee</TableHead>
                            <TableHead>Due</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Issue</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="task in tasks" :key="task.id">
                            <TableCell
                                ><Link
                                    :href="task.url"
                                    class="touch-target font-medium text-primary-text hover:underline"
                                    >{{ task.title }}</Link
                                ></TableCell
                            >
                            <TableCell>{{ task.assignee.name }}</TableCell>
                            <TableCell>
                                {{ formatDate(task.dueDate) }}
                                <OverdueBadge
                                    :days="task.daysOverdue"
                                    class="ml-1"
                                />
                            </TableCell>
                            <TableCell
                                ><TaskStatusBadge
                                    :status="task.status"
                                    :label="task.statusLabel"
                            /></TableCell>
                            <TableCell class="text-muted-foreground">{{
                                task.issue?.title ?? '—'
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <ul class="space-y-3 md:hidden">
                <li v-for="task in tasks" :key="task.id">
                    <TaskRow :task="task" />
                </li>
            </ul>

            <nav
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between"
                aria-label="Task pages"
            >
                <Button
                    variant="outline"
                    :disabled="!pagination.prev_url"
                    as-child
                >
                    <Link v-if="pagination.prev_url" :href="pagination.prev_url"
                        >Previous</Link
                    >
                    <span v-else>Previous</span>
                </Button>
                <span class="text-sm text-muted-foreground"
                    >Page {{ pagination.current_page }} of
                    {{ pagination.last_page }}</span
                >
                <Button
                    variant="outline"
                    :disabled="!pagination.next_url"
                    as-child
                >
                    <Link v-if="pagination.next_url" :href="pagination.next_url"
                        >Next</Link
                    >
                    <span v-else>Next</span>
                </Button>
            </nav>
        </template>
    </div>
</template>
