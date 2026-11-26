<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CircleCheck,
    CirclePlay,
    ListChecks,
    Search,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import AssigneeAvatar from '@/components/tasks/AssigneeAvatar.vue';
import TaskBoard from '@/components/tasks/TaskBoard.vue';
import TaskRow from '@/components/tasks/TaskRow.vue';
import TaskStatusBadge from '@/components/tasks/TaskStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import type {
    Pagination,
    TaskBoardData,
    TaskRowData,
    TaskStats,
    TaskStatusValue,
    TaskSummary,
} from '@/types';

const props = defineProps<{
    view: string;
    tasks: TaskRowData[] | null;
    pagination: Pagination | null;
    board: TaskBoardData | null;
    filters: {
        status: string | null;
        assignee: number | null;
        overdue: boolean;
        issue: number | null;
        q: string | null;
    };
    options: {
        assignees: { id: number; name: string }[];
        statuses: { value: string; label: string }[];
        issues: { id: number; title: string }[];
    };
    summary: TaskSummary;
    stats: TaskStats;
    businessToday: string;
}>();

const view = ref(props.view);
const status = ref(props.filters.status ?? '');
const assignee = ref(
    props.filters.assignee === null ? '' : String(props.filters.assignee),
);
const issue = ref(
    props.filters.issue === null ? '' : String(props.filters.issue),
);
const overdue = ref(props.filters.overdue);
const search = ref(props.filters.q ?? '');

function params(over: Record<string, string | number> = {}) {
    return {
        view: view.value,
        ...(status.value ? { status: status.value } : {}),
        ...(assignee.value ? { assignee: assignee.value } : {}),
        ...(issue.value ? { issue: issue.value } : {}),
        ...(overdue.value ? { overdue: 1 } : {}),
        ...(search.value.trim() ? { q: search.value.trim() } : {}),
        ...over,
    };
}

function apply(): void {
    router.get(index().url, params(), { preserveState: true, replace: true });
}

function go(next: string): void {
    view.value = next;
    apply();
}

function clear(): void {
    status.value = '';
    assignee.value = '';
    issue.value = '';
    overdue.value = false;
    search.value = '';
    apply();
}

// Search as you type, after a short pause.
let timer: ReturnType<typeof setTimeout> | null = null;

function onSearch(): void {
    if (timer !== null) {
        clearTimeout(timer);
    }

    timer = setTimeout(apply, 350);
}

onBeforeUnmount(() => timer !== null && clearTimeout(timer));

const filtered = (): boolean =>
    !!props.filters.status ||
    props.filters.assignee !== null ||
    props.filters.overdue ||
    props.filters.issue !== null ||
    !!props.filters.q;

const listUrl = (next: TaskStatusValue): string =>
    index({ query: { view: 'list', status: next } }).url;

const share = (count: number): string =>
    props.stats.total > 0
        ? `${Math.round((count / props.stats.total) * 100)}% of tasks`
        : '';

const hasTasks = (): boolean =>
    props.view === 'board'
        ? (props.board?.total ?? 0) > 0
        : (props.tasks?.length ?? 0) > 0;
</script>

<template>
    <Head title="Tasks" />

    <PageHeader
        title="Tasks"
        description="Assign work, follow its progress and see what is late."
    >
        <Button variant="outline" as-child>
            <Link href="/meetings">
                <Sparkles aria-hidden="true" />
                Review proposed tasks
                <span
                    v-if="stats.pendingDrafts > 0"
                    class="ml-1 rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground tabular-nums"
                    >{{ stats.pendingDrafts }}</span
                >
            </Link>
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <div
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
            role="status"
            aria-label="Task summary"
        >
            <MetricTile
                label="Total tasks"
                :value="String(stats.total)"
                :icon="ListChecks"
                :caption="`${summary.open} still open`"
            />
            <MetricTile
                label="In progress"
                :value="String(stats.inProgress)"
                :icon="CirclePlay"
                tone="info"
                :caption="share(stats.inProgress)"
            />
            <MetricTile
                label="Completed"
                :value="String(stats.completed)"
                :icon="CircleCheck"
                tone="success"
                :caption="share(stats.completed)"
            />
            <MetricTile
                label="Overdue"
                :value="String(stats.overdue)"
                :icon="TriangleAlert"
                :tone="stats.overdue > 0 ? 'danger' : 'success'"
                :caption="
                    stats.overdue > 0
                        ? 'Past their due date'
                        : 'Nothing is late'
                "
            />
            <MetricTile
                label="Proposed tasks"
                :value="String(stats.pendingDrafts)"
                :icon="Sparkles"
                tone="warning"
                caption="Waiting for your review"
            />
        </div>

        <Card class="gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div
                    class="inline-flex rounded-lg bg-muted p-1"
                    role="tablist"
                    aria-label="Task view"
                >
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="view === 'board'"
                        class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            view === 'board'
                                ? 'bg-card text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="go('board')"
                    >
                        Task board
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="view === 'list'"
                        class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            view === 'list'
                                ? 'bg-card text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="go('list')"
                    >
                        All tasks
                    </button>
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1">
                        <label
                            for="task-issue"
                            class="text-xs font-medium text-muted-foreground"
                            >Issue</label
                        >
                        <NativeSelect
                            id="task-issue"
                            v-model="issue"
                            class="min-w-44"
                            @update:model-value="apply"
                        >
                            <option value="">All issues</option>
                            <option
                                v-for="item in options.issues"
                                :key="item.id"
                                :value="String(item.id)"
                            >
                                {{ item.title }}
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
                            class="min-w-44"
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
                    <div class="grid gap-1">
                        <label
                            for="task-status"
                            class="text-xs font-medium text-muted-foreground"
                            >Status</label
                        >
                        <NativeSelect
                            id="task-status"
                            v-model="status"
                            class="min-w-40"
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
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            class="w-52 pl-9"
                            placeholder="Search tasks"
                            aria-label="Search tasks"
                            @input="onSearch"
                            @keydown.enter.prevent="apply"
                        />
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
                        class="mb-0.5"
                        @click="clear"
                        >Clear filters</Button
                    >
                </div>
            </div>
        </Card>

        <Card v-if="!hasTasks()">
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

        <template v-else-if="view === 'board' && board">
            <TaskBoard :board="board" :list-url="listUrl" />
            <p class="text-xs text-muted-foreground" role="status">
                Showing {{ board.shown }} of {{ board.total }} tasks on the
                board<template v-if="board.cancelled > 0">
                    · {{ board.cancelled }} cancelled not shown</template
                >.
            </p>
        </template>

        <template v-else-if="tasks && pagination">
            <Card class="hidden gap-0 overflow-hidden p-0 md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Task</TableHead>
                            <TableHead>Related issue</TableHead>
                            <TableHead>Assignee</TableHead>
                            <TableHead>Due date</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="task in tasks" :key="task.id">
                            <TableCell class="max-w-sm whitespace-normal">
                                <Link
                                    :href="task.url"
                                    class="touch-target font-medium text-foreground hover:text-primary-text hover:underline"
                                    >{{ task.title }}</Link
                                >
                                <p
                                    v-if="task.description"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ task.description }}
                                </p>
                            </TableCell>
                            <TableCell>
                                <span
                                    v-if="task.issue"
                                    class="inline-flex max-w-56 items-center gap-1.5 truncate rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground"
                                >
                                    <TriangleAlert
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        task.issue.title
                                    }}</span>
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </TableCell>
                            <TableCell>
                                <span class="inline-flex items-center gap-2">
                                    <AssigneeAvatar
                                        :name="task.assignee.name"
                                        size="sm"
                                    />
                                    {{ task.assignee.name }}
                                </span>
                            </TableCell>
                            <TableCell
                                :class="
                                    task.daysOverdue !== null &&
                                    task.daysOverdue > 0
                                        ? 'font-medium text-severity-high'
                                        : ''
                                "
                            >
                                {{ formatDate(task.dueDate) }}
                                <span
                                    v-if="
                                        task.daysOverdue !== null &&
                                        task.daysOverdue > 0
                                    "
                                    class="block text-xs"
                                    >(Overdue)</span
                                >
                            </TableCell>
                            <TableCell
                                ><TaskStatusBadge
                                    :status="task.status"
                                    :label="task.statusLabel"
                            /></TableCell>
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
