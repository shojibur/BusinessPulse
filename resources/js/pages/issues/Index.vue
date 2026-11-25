<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, ShieldCheck } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SeverityBadge from '@/components/SeverityBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
        <p class="text-sm text-muted-foreground" role="status">
            Latest evaluation:
            <span class="font-medium text-foreground">
                {{
                    lastEvaluatedAt
                        ? formatDateTime(lastEvaluatedAt)
                        : 'not evaluated yet'
                }}
            </span>
        </p>

        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1">
                <label for="status-filter" class="text-sm font-medium">
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
                <label for="severity-filter" class="text-sm font-medium">
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
        </div>

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
            <Card class="hidden gap-0 p-0 md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Issue</TableHead>
                            <TableHead>Severity</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Key figure</TableHead>
                            <TableHead>First detected</TableHead>
                            <TableHead>Last detected</TableHead>
                            <TableHead class="text-right">Detections</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="issue in issues" :key="issue.id">
                            <TableCell>
                                <Link
                                    :href="show(issue.id).url"
                                    class="touch-target font-medium text-primary-text hover:underline"
                                >
                                    {{ issue.title }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ issue.department }}
                                </p>
                            </TableCell>
                            <TableCell>
                                <SeverityBadge :severity="issue.severity" />
                            </TableCell>
                            <TableCell>
                                <IssueStatusBadge
                                    :status="issue.status"
                                    :label="issue.status_label"
                                />
                            </TableCell>
                            <TableCell class="tabular-nums">
                                <span class="text-muted-foreground">
                                    {{ issue.key_figure.label }}:
                                </span>
                                {{ issue.key_figure.value ?? '—' }}
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(issue.first_detected_at) }}
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(issue.last_detected_at) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ issue.detection_count }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <ul class="space-y-3 md:hidden">
                <li v-for="issue in issues" :key="issue.id">
                    <Card class="gap-2 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <SeverityBadge :severity="issue.severity" />
                            <IssueStatusBadge
                                :status="issue.status"
                                :label="issue.status_label"
                            />
                        </div>
                        <Link
                            :href="show(issue.id).url"
                            class="touch-target text-base font-semibold text-primary-text hover:underline"
                        >
                            {{ issue.title }}
                        </Link>
                        <p class="text-sm tabular-nums">
                            {{ issue.key_figure.label }}:
                            <span class="font-medium">
                                {{ issue.key_figure.value ?? '—' }}
                            </span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Detected {{ issue.detection_count }}×, last
                            {{ formatDateTime(issue.last_detected_at) }}
                        </p>
                    </Card>
                </li>
            </ul>

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
