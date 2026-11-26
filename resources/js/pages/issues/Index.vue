<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Banknote,
    CircleAlert,
    ClipboardList,
    DatabaseZap,
    Eye,
    RefreshCw,
    Search,
    ShieldCheck,
    Target,
    TrendingDown,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import { index, refresh, show } from '@/routes/issues';
import type {
    IssueCounts,
    IssueDepartmentOption,
    IssueRow,
    Pagination,
} from '@/types';

const props = defineProps<{
    issues: IssueRow[];
    counts: IssueCounts;
    departments: IssueDepartmentOption[];
    pagination: Pagination;
    filters: {
        status: string;
        severity: string | null;
        department: number | null;
        q: string | null;
    };
    hasAnyIssue: boolean;
    lastEvaluatedAt: string | null;
    refreshDepartmentId: number | null;
}>();

const status = ref(props.filters.status);
const severity = ref(props.filters.severity ?? '');
const department = ref(
    props.filters.department === null ? '' : String(props.filters.department),
);
const query = ref(props.filters.q ?? '');
const refreshing = ref(false);

const statuses = [
    { value: 'open_issues', label: 'Open issues' },
    { value: 'all', label: 'All issues' },
    { value: 'open', label: 'Open' },
    { value: 'acknowledged', label: 'Acknowledged' },
    { value: 'action_in_progress', label: 'Action in progress' },
    { value: 'monitoring', label: 'Monitoring' },
    { value: 'resolved', label: 'Resolved' },
];

function applyFilters(): void {
    router.get(
        index().url,
        {
            status: status.value,
            ...(severity.value ? { severity: severity.value } : {}),
            ...(department.value ? { department: department.value } : {}),
            ...(query.value.trim() ? { q: query.value.trim() } : {}),
        },
        { preserveState: true, replace: true },
    );
}

// Search as you type, after a short pause.
let timer: ReturnType<typeof setTimeout> | null = null;

watch(query, () => {
    if (timer !== null) {
        clearTimeout(timer);
    }

    timer = setTimeout(applyFilters, 350);
});

function clearFilters(): void {
    status.value = 'open_issues';
    severity.value = '';
    department.value = '';
    query.value = '';
    applyFilters();
}

function refreshIntelligence(): void {
    refreshing.value = true;
    router.post(
        refresh().url,
        { department_id: props.refreshDepartmentId },
        { preserveScroll: true, onFinish: () => (refreshing.value = false) },
    );
}

const filtered = computed(
    () =>
        props.filters.status !== 'open_issues' ||
        props.filters.severity !== null ||
        props.filters.department !== null ||
        props.filters.q !== null,
);

const percentOfOpen = (count: number): string | undefined =>
    props.counts.open > 0
        ? `${Math.round((count / props.counts.open) * 100)}% of open`
        : undefined;

// One icon per kind of issue, tinted by severity.
const ruleIcons: Record<string, Component> = {
    weekly_revenue_decline: TrendingDown,
    sales_target_shortfall: Target,
    missing_sales_data: DatabaseZap,
};

const tileTone: Record<string, string> = {
    high: 'bg-severity-high-bg text-severity-high',
    medium: 'bg-severity-medium-bg text-severity-medium',
    low: 'bg-muted text-muted-foreground',
};

const dotTone: Record<string, string> = {
    high: 'bg-severity-high',
    medium: 'bg-severity-medium',
    low: 'bg-slate-400',
};

const severityLabel: Record<string, string> = {
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

const chipIcon: Record<string, Component> = {
    money: Banknote,
    down: ArrowDown,
    up: ArrowUp,
    target: Target,
    coverage: DatabaseZap,
};

const chipTone = (kind: string): string =>
    kind === 'down'
        ? 'bg-severity-high-bg text-severity-high'
        : kind === 'up'
          ? 'bg-success-bg text-success'
          : 'bg-muted text-foreground';

const outcomeTone = (result: string): string =>
    result === 'improved'
        ? 'bg-success-bg text-success'
        : result === 'worsened'
          ? 'bg-severity-high-bg text-severity-high'
          : 'bg-muted text-muted-foreground';
</script>

<template>
    <Head title="Issues" />

    <PageHeader
        title="Business issues"
        description="Problems found in your sales data, with the evidence behind each one."
    >
        <Button :disabled="refreshing" @click="refreshIntelligence">
            <Spinner v-if="refreshing" />
            <RefreshCw v-else aria-hidden="true" />
            Refresh intelligence
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <div
            v-if="hasAnyIssue"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <MetricTile
                label="Open issues"
                :value="String(counts.open)"
                :icon="TriangleAlert"
                tone="danger"
                :chip="
                    counts.new_this_week > 0
                        ? `${counts.new_this_week} new this week`
                        : undefined
                "
                chip-tone="warning"
                :caption="
                    lastEvaluatedAt
                        ? `Evaluated ${formatDateTime(lastEvaluatedAt)}`
                        : 'Not evaluated yet'
                "
            />
            <MetricTile
                label="High severity"
                :value="String(counts.high)"
                :icon="CircleAlert"
                :tone="counts.high > 0 ? 'warning' : 'neutral'"
                caption="Needs attention first"
            />
            <MetricTile
                label="In action"
                :value="String(counts.in_action)"
                :icon="ClipboardList"
                tone="success"
                :chip="percentOfOpen(counts.in_action)"
                chip-tone="success"
            />
            <MetricTile
                label="Monitoring"
                :value="String(counts.monitoring)"
                :icon="Eye"
                tone="info"
                :chip="percentOfOpen(counts.monitoring)"
                chip-tone="info"
            />
        </div>
        <p v-else class="text-sm text-muted-foreground" role="status">
            Latest evaluation:
            <span class="font-medium text-foreground">
                {{
                    lastEvaluatedAt
                        ? formatDateTime(lastEvaluatedAt)
                        : 'not evaluated yet'
                }}
            </span>
        </p>

        <!-- Filters in one row. -->
        <Card class="p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-56 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="query"
                        type="search"
                        class="pl-9"
                        placeholder="Search issues by title"
                        aria-label="Search issues by title"
                    />
                </div>
                <div v-if="departments.length > 1" class="w-44">
                    <NativeSelect
                        v-model="department"
                        aria-label="Department"
                        @update:model-value="applyFilters"
                    >
                        <option value="">All departments</option>
                        <option
                            v-for="d in departments"
                            :key="d.id"
                            :value="String(d.id)"
                        >
                            {{ d.name }}
                        </option>
                    </NativeSelect>
                </div>
                <div class="w-40">
                    <NativeSelect
                        v-model="severity"
                        aria-label="Severity"
                        @update:model-value="applyFilters"
                    >
                        <option value="">All severities</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </NativeSelect>
                </div>
                <div class="w-48">
                    <NativeSelect
                        v-model="status"
                        aria-label="Status"
                        @update:model-value="applyFilters"
                    >
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </div>
                <Button
                    v-if="filtered"
                    variant="ghost"
                    size="sm"
                    @click="clearFilters"
                >
                    Clear filters
                </Button>
            </div>
        </Card>

        <Card v-if="issues.length === 0">
            <EmptyState
                v-if="!hasAnyIssue"
                :icon="ShieldCheck"
                message="No issues detected. Issues appear here when the rules find a problem in imported sales data."
            />
            <EmptyState v-else message="No issues match the selected filters.">
                <Button variant="outline" @click="clearFilters">
                    Clear filters
                </Button>
            </EmptyState>
        </Card>

        <template v-else>
            <Card class="gap-0 overflow-hidden p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Issue</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Severity</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Detected</TableHead>
                            <TableHead>Linked tasks</TableHead>
                            <TableHead>Latest outcome</TableHead>
                            <TableHead class="w-12">
                                <span class="sr-only">Open</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="issue in issues" :key="issue.id">
                            <TableCell class="min-w-80 whitespace-normal">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                                        :class="tileTone[issue.severity]"
                                    >
                                        <component
                                            :is="
                                                ruleIcons[issue.rule_key] ??
                                                TriangleAlert
                                            "
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div class="min-w-0">
                                        <Link
                                            :href="show(issue.id).url"
                                            class="text-sm/5 font-semibold text-foreground hover:underline"
                                        >
                                            {{ issue.title }}
                                        </Link>
                                        <p
                                            v-if="issue.summary"
                                            class="text-xs/4 text-muted-foreground"
                                        >
                                            {{ issue.summary }}
                                        </p>
                                        <div
                                            v-if="issue.chips.length"
                                            class="mt-1.5 flex flex-wrap gap-1.5"
                                        >
                                            <span
                                                v-for="chip in issue.chips"
                                                :key="chip.kind + chip.label"
                                                class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium tabular-nums"
                                                :class="chipTone(chip.kind)"
                                            >
                                                <component
                                                    :is="
                                                        chipIcon[chip.kind] ??
                                                        Banknote
                                                    "
                                                    class="size-3"
                                                    aria-hidden="true"
                                                />
                                                {{ chip.label }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span
                                    class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium"
                                    >{{ issue.department }}</span
                                >
                            </TableCell>
                            <TableCell>
                                <span
                                    class="inline-flex items-center gap-2 text-sm font-medium"
                                >
                                    <span
                                        class="size-2 rounded-full"
                                        :class="dotTone[issue.severity]"
                                        aria-hidden="true"
                                    />
                                    {{ severityLabel[issue.severity] }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <IssueStatusBadge
                                    :status="issue.status"
                                    :label="issue.status_label"
                                />
                            </TableCell>
                            <TableCell class="text-sm tabular-nums">
                                {{ formatDate(issue.first_detected_at) }}
                            </TableCell>
                            <TableCell class="min-w-28">
                                <template v-if="issue.tasks.total > 0">
                                    <p class="text-sm font-medium tabular-nums">
                                        {{ issue.tasks.completed }}/{{
                                            issue.tasks.total
                                        }}
                                    </p>
                                    <div
                                        class="mt-1 h-1.5 w-20 overflow-hidden rounded-full bg-muted"
                                        role="presentation"
                                    >
                                        <div
                                            class="h-full rounded-full bg-primary"
                                            :style="{
                                                width: `${Math.round((issue.tasks.completed / issue.tasks.total) * 100)}%`,
                                            }"
                                        />
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </TableCell>
                            <TableCell>
                                <Link
                                    v-if="issue.latest_outcome"
                                    :href="issue.latest_outcome.url"
                                    class="inline-block rounded-lg px-2.5 py-1.5 text-xs/4"
                                    :class="
                                        outcomeTone(issue.latest_outcome.result)
                                    "
                                >
                                    <span class="block font-semibold">
                                        {{ issue.latest_outcome.label
                                        }}<template
                                            v-if="issue.latest_outcome.change"
                                        >
                                            {{ issue.latest_outcome.change }}
                                        </template>
                                    </span>
                                    <span
                                        v-if="issue.latest_outcome.followup"
                                        class="block opacity-80"
                                        >{{
                                            issue.latest_outcome.followup
                                        }}</span
                                    >
                                </Link>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </TableCell>
                            <TableCell>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                    :aria-label="`Open ${issue.title}`"
                                >
                                    <Link :href="show(issue.id).url">
                                        <Eye aria-hidden="true" />
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <nav
                v-if="pagination.last_page > 1"
                aria-label="Issue pages"
                class="flex items-center justify-between text-sm"
            >
                <span class="text-muted-foreground tabular-nums">
                    Showing {{ issues.length }} of {{ pagination.total }} · Page
                    {{ pagination.current_page }} of
                    {{ pagination.last_page }}
                </span>
                <span class="flex gap-4">
                    <Link
                        v-if="pagination.prev_url"
                        :href="pagination.prev_url"
                        class="touch-target font-medium text-primary-text hover:underline"
                    >
                        Previous
                    </Link>
                    <Link
                        v-if="pagination.next_url"
                        :href="pagination.next_url"
                        class="touch-target font-medium text-primary-text hover:underline"
                    >
                        Next
                    </Link>
                </span>
            </nav>
            <p v-else class="text-sm text-muted-foreground tabular-nums">
                Showing {{ issues.length }} of {{ pagination.total }} issues
            </p>
        </template>

        <p v-if="filtered" class="sr-only" role="status">Filters applied.</p>
    </div>
</template>
