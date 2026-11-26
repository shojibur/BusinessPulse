<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    CircleAlert,
    Clock,
    FileText,
    LayoutDashboard,
    ListChecks,
    Target,
    TrendingDown,
    TrendingUp,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EChart from '@/components/EChart.vue';
import EmptyState from '@/components/EmptyState.vue';
import InsufficientDataNotice from '@/components/InsufficientDataNotice.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import ExecutiveBriefBanner from '@/components/dashboard/ExecutiveBriefBanner.vue';
import OutcomeSummaryCard from '@/components/dashboard/OutcomeSummaryCard.vue';
import RecentActivityCard from '@/components/dashboard/RecentActivityCard.vue';
import TaskStatusCard from '@/components/dashboard/TaskStatusCard.vue';
import UrgentFindingsCard from '@/components/dashboard/UrgentFindingsCard.vue';
import type { DashboardExtras } from '@/components/dashboard/types';
import { Spinner } from '@/components/ui/spinner';
import { chartPalette } from '@/lib/chartPalette';
import {
    formatDate,
    formatDateRange,
    formatDateTime,
    formatMoney,
} from '@/lib/format';
import { store as briefStore } from '@/routes/briefs';
import { index as importsIndex } from '@/routes/imports';
import { index as tasksIndex } from '@/routes/tasks';
import { index as issuesIndex } from '@/routes/issues';
import type { DashboardData, DashboardFigure } from '@/types';

const props = defineProps<{
    audience: 'staff' | 'owner_manager';
    data?: (DashboardData & DashboardExtras) | null;
    ai?: { text: boolean };
}>();

const generating = ref(false);
const error = ref<string | null>(null);

function generateBrief(): void {
    generating.value = true;
    error.value = null;
    router.post(
        briefStore().url,
        {},
        {
            onError: (bag) =>
                (error.value =
                    bag.brief ?? 'The brief could not be requested.'),
            onFinish: () => (generating.value = false),
        },
    );
}

const period = (figure: DashboardFigure): string =>
    formatDateRange(figure.period_start, figure.period_end);

const value = (figure: DashboardFigure): string =>
    figure.available ? figure.display : '—';

// The change figure, as a short caption and a tone (down is bad, up is good).
const change = computed(() => {
    const figure = props.data?.change;

    if (!figure?.available) {
        return { caption: undefined, tone: 'neutral' as const, down: false };
    }

    const n = Number(figure.value);

    return {
        caption: `${n > 0 ? '+' : ''}${figure.value}% vs previous week`,
        tone:
            n < 0
                ? ('danger' as const)
                : n > 0
                  ? ('success' as const)
                  : ('neutral' as const),
        down: n < 0,
    };
});

const firstName = computed(
    () => usePage().props.auth.user?.name.split(' ')[0] ?? '',
);

const notices = computed(() => {
    const seen = new Set<string>();
    const result: string[] = [];

    for (const figure of [
        props.data?.revenue,
        props.data?.change,
        props.data?.targetAchievement,
    ]) {
        if (figure && !figure.available && !seen.has(figure.message)) {
            seen.add(figure.message);
            result.push(figure.message);
        }
    }

    return result;
});

const evaluationAlert = computed(() => {
    const evaluation = props.data?.evaluation;

    if (!evaluation || props.data?.freshness.behind) {
        return null;
    }

    const at = evaluation.last_evaluated_at
        ? formatDateTime(evaluation.last_evaluated_at)
        : null;

    return {
        failed: `The last evaluation run failed, so issues may be missing or out of date${at ? ` (last successful: ${at})` : ''}.`,
        partial: `The last evaluation could not evaluate ${evaluation.failed_rules} rule(s), so issues may be incomplete.`,
        stale: 'Sales data changed after the last evaluation, so issue counts may be out of date.',
        never: 'No evaluation has run yet, so no issues have been checked.',
        current: null,
    }[evaluation.status];
});

const series = computed(() => props.data?.revenueVsTarget ?? []);

const chartOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    legend: { bottom: 0 },
    grid: { left: 8, right: 16, top: 24, bottom: 36, containLabel: true },
    xAxis: {
        type: 'category',
        boundaryGap: false,
        data: series.value.map((p) => p.label),
    },
    yAxis: {
        type: 'value',
        axisLabel: { formatter: (v: number) => `${v / 1000}k` },
        splitLine: { lineStyle: { color: '#e3e8ee' } },
    },
    series: [
        {
            name: 'Revenue',
            type: 'line',
            smooth: true,
            symbolSize: 8,
            data: series.value.map((p) =>
                p.revenue === null ? null : Number(p.revenue),
            ),
            lineStyle: { color: chartPalette.teal, width: 3 },
            itemStyle: { color: chartPalette.teal },
            areaStyle: { color: chartPalette.teal, opacity: 0.12 },
        },
        {
            name: 'Target',
            type: 'line',
            symbol: 'none',
            data: series.value.map((p) =>
                p.target === null ? null : Number(p.target),
            ),
            lineStyle: { color: chartPalette.slate, type: 'dashed', width: 2 },
            itemStyle: { color: chartPalette.slate },
        },
    ],
}));

const chartTable = computed(() => ({
    columns: ['Week', 'Revenue', 'Target'],
    rows: series.value.map((p) => [
        p.label,
        p.is_complete ? formatMoney(p.revenue) : 'Incomplete',
        p.is_complete ? formatMoney(p.target) : 'Incomplete',
    ]),
}));

// A time-of-day greeting, then the first name.
const greeting = computed(() => {
    const hour = new Date().getHours();
    const part = hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';

    return `Good ${part}${firstName.value ? `, ${firstName.value}` : ''}`;
});

// The weekly change as a short pill beside the revenue figure.
const changeChip = computed(() => {
    const figure = props.data?.change;

    if (!figure?.available) {
        return undefined;
    }

    const n = Number(figure.value);

    return `${n > 0 ? '+' : ''}${figure.value}%`;
});

const targetChip = computed(() =>
    props.data?.targetAchievement.available
        ? `Target ${props.data.targetAchievement.target}`
        : undefined,
);
</script>

<template>
    <Head title="Today" />

    <PageHeader
        :title="greeting"
        :description="
            data
                ? `${data.departmentName} · your business performance, key actions and outcomes.`
                : undefined
        "
    >
        <Button
            v-if="audience === 'owner_manager' && data"
            :disabled="generating"
            @click="generateBrief"
        >
            <Spinner v-if="generating" />
            <FileText v-else aria-hidden="true" />
            Generate brief
        </Button>
    </PageHeader>

    <Card v-if="audience === 'staff'">
        <EmptyState
            :icon="LayoutDashboard"
            message="Your tasks are in the Tasks section. A summary of your day will appear here."
        />
    </Card>

    <Card v-else-if="!data">
        <EmptyState
            :icon="LayoutDashboard"
            message="No department is set up yet."
        />
    </Card>

    <div v-else class="space-y-6">
        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>

        <!-- Slim notices: only when something needs attention. -->
        <Alert v-if="data.freshness.behind" role="status">
            <Clock aria-hidden="true" />
            <AlertTitle>{{ data.freshness.message }}</AlertTitle>
            <AlertDescription v-if="data.freshness.latest_sales_date === null">
                <Link
                    :href="importsIndex().url"
                    class="text-primary-text underline"
                    >Go to Data Imports</Link
                >
            </AlertDescription>
            <AlertDescription v-else>
                Latest sales date:
                {{ formatDate(data.freshness.latest_sales_date) }}.
            </AlertDescription>
        </Alert>

        <Alert v-if="evaluationAlert" role="status">
            <TriangleAlert aria-hidden="true" />
            <AlertTitle>Intelligence needs attention</AlertTitle>
            <AlertDescription>
                {{ evaluationAlert }}
                <Link
                    :href="issuesIndex().url"
                    class="text-primary-text underline"
                    >Open Issues to refresh</Link
                >
            </AlertDescription>
        </Alert>

        <Alert v-if="data.coverageNotice" role="status">
            <CircleAlert aria-hidden="true" />
            <AlertTitle>The latest closed week is not complete</AlertTitle>
            <AlertDescription>{{ data.coverageNotice }}</AlertDescription>
        </Alert>

        <ExecutiveBriefBanner
            :brief="data.latestBrief"
            :ai-configured="ai?.text ?? false"
        />

        <!-- The four numbers an owner checks first. -->
        <section
            aria-label="Key figures"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <MetricTile
                label="Revenue"
                :value="value(data.revenue)"
                :caption="period(data.revenue)"
                :icon="change.down ? TrendingDown : Banknote"
                :chip="changeChip"
                :chip-tone="change.tone"
            />
            <MetricTile
                label="Target achievement"
                :value="value(data.targetAchievement)"
                :caption="period(data.targetAchievement)"
                :icon="Target"
                :chip="targetChip"
            />
            <Link
                :href="issuesIndex({ query: { status: 'open_issues' } }).url"
                class="block rounded-xl transition-transform hover:-translate-y-0.5 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <MetricTile
                    label="Open issues"
                    :value="String(data.openIssueCount)"
                    caption="View all open issues"
                    :icon="TriangleAlert"
                    :tone="data.openIssueCount > 0 ? 'warning' : 'success'"
                    :chip="
                        data.highPriorityIssueCount > 0
                            ? `${data.highPriorityIssueCount} urgent`
                            : undefined
                    "
                    chip-tone="danger"
                />
            </Link>
            <Link
                :href="tasksIndex({ query: { status: 'open' } }).url"
                class="block rounded-xl transition-transform hover:-translate-y-0.5 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <MetricTile
                    label="Active tasks"
                    :value="String(data.taskCounts.open)"
                    :caption="
                        data.taskCounts.overdue > 0
                            ? `${data.taskCounts.overdue} overdue`
                            : 'None overdue'
                    "
                    :icon="ListChecks"
                    tone="info"
                    :chip="
                        data.taskStatus.in_progress > 0
                            ? `${data.taskStatus.in_progress} in progress`
                            : undefined
                    "
                    chip-tone="info"
                />
            </Link>
        </section>

        <InsufficientDataNotice
            v-for="message in notices"
            :key="message"
            :message="message"
        />

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
            <Card class="gap-4 p-5">
                <SectionHeader
                    title="Revenue trend"
                    description="Weekly revenue against target, most recent closed weeks."
                    :icon="TrendingUp"
                />
                <EChart
                    :option="chartOption"
                    ariaLabel="Weekly revenue compared with target for the most recent closed weeks"
                    :table-data="chartTable"
                    :is-empty="series.length === 0"
                    empty-message="No closed week with sales data yet. Upload sales data in Data Imports."
                />
            </Card>
            <UrgentFindingsCard :issues="data.urgentIssues" />
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-2 xl:grid-cols-3">
            <TaskStatusCard :status="data.taskStatus" />
            <OutcomeSummaryCard :outcome="data.outcome" />
            <RecentActivityCard :entries="data.activity" />
        </div>
    </div>
</template>
