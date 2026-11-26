<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronRight,
    CircleAlert,
    Layers,
    RefreshCw,
    ShieldCheck,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import SeverityBadge from '@/components/SeverityBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { index, refresh, show } from '@/routes/issues';
import type { IssueRow, Pagination } from '@/types';

const props = defineProps<{
    issues: IssueRow[];
    pagination: Pagination;
    filters: { status: string; severity: string | null };
    hasAnyIssue: boolean;
    lastEvaluatedAt: string | null;
    refreshDepartmentId: number | null;
}>();

const status = ref(props.filters.status);
const severity = ref(props.filters.severity ?? '');
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
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    status.value = 'open_issues';
    severity.value = '';
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

const filtered = (): boolean =>
    props.filters.status !== 'open_issues' || props.filters.severity !== null;

// Per-severity counts are only exact when everything fits on one page.
const onePage = computed(() => props.pagination.last_page <= 1);
const countOf = (severity: 'high' | 'medium' | 'low'): number =>
    props.issues.filter((issue) => issue.severity === severity).length;

const edge: Record<string, string> = {
    high: 'border-l-severity-high',
    medium: 'border-l-severity-medium',
    low: 'border-l-slate-300',
};
</script>

<template>
    <Head title="Issues" />

    <PageHeader
        title="Issues"
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
                label="Issues in this view"
                :value="String(pagination.total)"
                :caption="
                    lastEvaluatedAt
                        ? `Evaluated ${formatDateTime(lastEvaluatedAt)}`
                        : 'Not evaluated yet'
                "
                :icon="Layers"
            />
            <template v-if="onePage">
                <MetricTile
                    label="High severity"
                    :value="String(countOf('high'))"
                    caption="Needs attention first"
                    :icon="CircleAlert"
                    :tone="countOf('high') > 0 ? 'danger' : 'neutral'"
                />
                <MetricTile
                    label="Medium severity"
                    :value="String(countOf('medium'))"
                    caption="Worth watching"
                    :icon="TriangleAlert"
                    :tone="countOf('medium') > 0 ? 'warning' : 'neutral'"
                />
                <MetricTile
                    label="Low severity"
                    :value="String(countOf('low'))"
                    caption="For information"
                    :icon="ShieldCheck"
                />
            </template>
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

        <Card class="gap-4 p-5">
            <SectionHeader title="Filter" />
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1">
                    <label
                        for="status-filter"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Status
                    </label>
                    <NativeSelect
                        id="status-filter"
                        v-model="status"
                        class="min-w-48"
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
                <div class="grid gap-1">
                    <label
                        for="severity-filter"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Severity
                    </label>
                    <NativeSelect
                        id="severity-filter"
                        v-model="severity"
                        class="min-w-40"
                        @update:model-value="applyFilters"
                    >
                        <option value="">All severities</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </NativeSelect>
                </div>
                <Button
                    v-if="filtered()"
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
                <ul class="divide-y">
                    <li
                        v-for="issue in issues"
                        :key="issue.id"
                        class="border-l-4 transition-colors hover:bg-muted/40"
                        :class="edge[issue.severity]"
                    >
                        <Link
                            :href="show(issue.id).url"
                            class="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-4 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:ring-inset"
                        >
                            <div class="min-w-0 flex-1 basis-64">
                                <p
                                    class="truncate text-sm/5 font-semibold text-foreground"
                                >
                                    {{ issue.title }}
                                </p>
                                <p class="text-xs/4 text-muted-foreground">
                                    {{ issue.department }} · Detected
                                    {{ issue.detection_count }}×, last
                                    {{ formatDateTime(issue.last_detected_at) }}
                                </p>
                            </div>
                            <div class="min-w-32 text-sm tabular-nums">
                                <p class="text-xs text-muted-foreground">
                                    {{ issue.key_figure.label }}
                                </p>
                                <p class="font-semibold">
                                    {{ issue.key_figure.value ?? '—' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <SeverityBadge :severity="issue.severity" />
                                <IssueStatusBadge
                                    :status="issue.status"
                                    :label="issue.status_label"
                                />
                            </div>
                            <ChevronRight
                                class="hidden size-4 text-muted-foreground sm:block"
                                aria-hidden="true"
                            />
                        </Link>
                    </li>
                </ul>
            </Card>

            <nav
                v-if="pagination.last_page > 1"
                aria-label="Issue pages"
                class="flex items-center justify-between text-sm"
            >
                <span class="text-muted-foreground tabular-nums">
                    Page {{ pagination.current_page }} of
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
        </template>

        <p v-if="filtered()" class="sr-only" role="status">Filters applied.</p>
    </div>
</template>
