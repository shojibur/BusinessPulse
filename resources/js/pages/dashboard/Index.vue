<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    CircleAlert,
    Clock,
    FileText,
    LayoutDashboard,
    ListChecks,
    OctagonAlert,
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
import { Spinner } from '@/components/ui/spinner';
import { chartPalette } from '@/lib/chartPalette';
import {
    formatDate,
    formatDateRange,
    formatDateTime,
    formatMoney,
} from '@/lib/format';
import {
    index as briefsIndex,
    show as briefShow,
    store as briefStore,
} from '@/routes/briefs';
import { index as importsIndex } from '@/routes/imports';
import { index as tasksIndex } from '@/routes/tasks';
import { index as issuesIndex } from '@/routes/issues';
import type { DashboardData, DashboardFigure } from '@/types';
import BriefSourceBadge from '@/components/BriefSourceBadge.vue';

const props = defineProps<{
    audience: 'staff' | 'owner_manager';
    data?: DashboardData | null;
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
    grid: { left: 8, right: 8, top: 16, bottom: 36, containLabel: true },
    xAxis: { type: 'category', data: series.value.map((p) => p.label) },
    yAxis: {
        type: 'value',
        axisLabel: { formatter: (v: number) => `${v / 1000}k` },
    },
    series: [
        {
            name: 'Revenue',
            type: 'bar',
            data: series.value.map((p) =>
                p.revenue === null ? null : Number(p.revenue),
            ),
            itemStyle: { color: chartPalette.teal },
        },
        {
            name: 'Target',
            type: 'line',
            data: series.value.map((p) =>
                p.target === null ? null : Number(p.target),
            ),
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
</script>

<template>
    <Head title="Today" />

    <PageHeader
        title="Today"
        :description="
            data
                ? `${firstName ? `Hello, ${firstName}. ` : ''}${data.departmentName} · business overview`
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

        <!-- The money: the three figures an owner checks first. -->
        <section aria-label="Key figures" class="grid gap-4 sm:grid-cols-3">
            <MetricTile
                label="Revenue"
                :value="value(data.revenue)"
                :caption="period(data.revenue)"
                :icon="Banknote"
            />
            <MetricTile
                label="Weekly change"
                :value="value(data.change)"
                :caption="change.caption ?? period(data.change)"
                :icon="change.down ? TrendingDown : TrendingUp"
                :tone="change.tone"
            />
            <MetricTile
                label="Target achievement"
                :value="value(data.targetAchievement)"
                :caption="
                    data.targetAchievement.available
                        ? `Target ${data.targetAchievement.target}`
                        : period(data.targetAchievement)
                "
                :icon="Target"
            />
        </section>

        <!-- What needs doing: each tile opens the matching list. -->
        <section
            aria-label="Issues and tasks"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
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
                />
            </Link>
            <Link
                :href="
                    issuesIndex({
                        query: { status: 'open_issues', severity: 'high' },
                    }).url
                "
                class="block rounded-xl transition-transform hover:-translate-y-0.5 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <MetricTile
                    label="High priority"
                    :value="String(data.highPriorityIssueCount)"
                    caption="View high-priority issues"
                    :icon="OctagonAlert"
                    :tone="
                        data.highPriorityIssueCount > 0 ? 'danger' : 'success'
                    "
                />
            </Link>
            <Link
                :href="tasksIndex({ query: { status: 'open' } }).url"
                class="block rounded-xl transition-transform hover:-translate-y-0.5 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <MetricTile
                    label="Open tasks"
                    :value="String(data.taskCounts.open)"
                    caption="View open tasks"
                    :icon="ListChecks"
                    tone="info"
                />
            </Link>
            <Link
                :href="tasksIndex({ query: { overdue: 1 } }).url"
                class="block rounded-xl transition-transform hover:-translate-y-0.5 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <MetricTile
                    label="Overdue tasks"
                    :value="String(data.taskCounts.overdue)"
                    caption="View overdue tasks"
                    :icon="Clock"
                    :tone="data.taskCounts.overdue > 0 ? 'danger' : 'success'"
                />
            </Link>
        </section>

        <InsufficientDataNotice
            v-for="message in notices"
            :key="message"
            :message="message"
        />

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <Card class="gap-4 p-5">
                <SectionHeader
                    title="Revenue vs target by week"
                    description="The most recent closed weeks."
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

            <Card class="gap-4 p-5">
                <SectionHeader title="Latest brief" :icon="FileText">
                    <Link
                        :href="briefsIndex().url"
                        class="text-sm text-primary-text hover:underline"
                        >All briefs</Link
                    >
                </SectionHeader>
                <template v-if="data.latestBrief">
                    <div class="flex flex-wrap items-center gap-2">
                        <BriefSourceBadge
                            status="completed"
                            :source="data.latestBrief.source"
                        />
                        <span class="text-xs text-muted-foreground">{{
                            formatDateTime(data.latestBrief.generated_at)
                        }}</span>
                    </div>
                    <p class="text-sm/6 font-medium">
                        {{ data.latestBrief.headline }}
                    </p>
                    <Button variant="outline" as-child>
                        <Link :href="briefShow(data.latestBrief.id).url"
                            >Open brief</Link
                        >
                    </Button>
                </template>
                <p v-else class="text-sm text-muted-foreground">
                    No brief has been generated yet.
                </p>
            </Card>
        </div>
    </div>
</template>
