<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    CalendarDays,
    ChevronLeft,
    CircleCheck,
    CircleX,
    DatabaseZap,
    Gauge,
    Hash,
    History,
    Info,
    Minus,
    RefreshCw,
    ShieldCheck,
    Target,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import EvaluationEvidence from '@/components/EvaluationEvidence.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import MetricTile from '@/components/MetricTile.vue';
import OutcomeComparisonChart from '@/components/outcomes/OutcomeComparisonChart.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import TimelineList from '@/components/TimelineList.vue';
import type { TimelineItem } from '@/components/TimelineList.vue';
import SeverityBadge from '@/components/SeverityBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import RelatedMeetingsPanel from '@/components/issues/RelatedMeetingsPanel.vue';
import FeedbackPanel from '@/components/issues/FeedbackPanel.vue';
import OutcomePanel from '@/components/issues/OutcomePanel.vue';
import RelatedTasksPanel from '@/components/issues/RelatedTasksPanel.vue';
import TracePanel from '@/components/issues/TracePanel.vue';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { index, show } from '@/routes/issues';
import { acknowledge, monitoring, refresh, resolve } from '@/routes/issues';
import type {
    EvaluationView,
    IssueChart,
    IssueDetail,
    IssueEligibility,
    IssueMeetings,
    IssueTasks,
    FeedbackPanelData,
    OutcomePanelData,
    TracePanelData,
    StatusHistoryEntry,
} from '@/types';

const props = defineProps<{
    issue: IssueDetail;
    baseline: EvaluationView;
    latest: EvaluationView;
    latestOfRule: EvaluationView | null;
    meetings: IssueMeetings | null;
    tasks: IssueTasks | null;
    outcomes?: OutcomePanelData | null;
    feedback?: FeedbackPanelData | null;
    trace?: TracePanelData | null;
    statusHistory: StatusHistoryEntry[];
    actions: { acknowledge: boolean; monitoring: boolean; resolve: boolean };
    lastEvaluatedAt: string | null;
    stale: boolean;
    chart: IssueChart | null;
    eligibility: IssueEligibility | null;
    evaluationStatus: 'never' | 'current' | 'partial' | 'failed' | 'stale';
    refreshDepartmentId: number;
}>();

const busy = ref(false);
const monitoringReason = ref('');
const resolveReason = ref('');
const errors = ref<Record<string, string>>({});
const errorFor = ref<'monitoring' | 'resolve' | null>(null);

function post(
    url: string,
    data: Record<string, string | number | null> = {},
    form: 'monitoring' | 'resolve' | null = null,
): void {
    busy.value = true;
    errorFor.value = form;
    router.post(url, data, {
        preserveScroll: true,
        onError: (bag) => (errors.value = bag),
        onSuccess: () => {
            errors.value = {};
            monitoringReason.value = '';
            resolveReason.value = '';
        },
        onFinish: () => (busy.value = false),
    });
}

const latestText = computed(() => {
    const e = props.latestOfRule;

    if (e === null || e.result === 'breached') {
        return null;
    }

    return e.result === 'not_breached'
        ? 'Latest evaluation: not breached'
        : `Latest evaluation: insufficient data${e.insufficient_reason_text ? ` (${e.insufficient_reason_text})` : ''}`;
});

const sameEvaluation = computed(() => props.baseline.id === props.latest.id);

const anyAction = computed(
    () =>
        props.actions.acknowledge ||
        props.actions.monitoring ||
        props.actions.resolve,
);

const triggerLabel = (trigger: string | null): string =>
    ({
        created: 'First detected',
        manual: 'By a user',
        re_detection: 'Detected again',
        task_approved: 'Action started',
        tasks_complete: 'All tasks completed',
        task_reopened: 'A task was reopened',
        outcome_verified: 'Recovery verified',
        outcome_worsened: 'Outcome worsened',
    })[trigger ?? ''] ?? '';

// The headline figures of the latest evaluation, for a business owner to read in a few seconds.
type Tile = {
    label: string;
    value: string;
    caption?: string;
    icon: Component;
    tone: 'neutral' | 'danger' | 'success' | 'warning' | 'info';
    chip?: string;
    chipTone?: 'neutral' | 'danger' | 'success' | 'warning' | 'info';
};

const outcomeTiles = computed<Tile[] | null>(() => {
    const o = props.outcomes?.latest;

    if (!o || o.notEvaluableText) {
        return null;
    }

    const tone: Tile['tone'] =
        o.result === 'improved'
            ? 'success'
            : o.result === 'worsened'
              ? 'danger'
              : 'neutral';
    const list: Tile[] = [];

    // The week before the problem, when the compared periods are known.
    if (props.chart?.values[0]) {
        list.push({
            label: 'Before the issue',
            value: formatMoney(props.chart.values[0]),
            caption: props.chart.categories[0]?.replace('Before ', ''),
            icon: CalendarDays,
            tone: 'neutral',
        });
    }

    list.push(
        {
            label: 'At detection',
            value: o.baselineLabel ?? '—',
            caption: props.chart?.categories[1]?.replace('Baseline ', ''),
            icon: Banknote,
            tone: 'neutral',
            chip:
                props.baseline.difference_pct === null
                    ? undefined
                    : `${props.baseline.difference_pct}%`,
            chipTone: 'danger',
        },
        {
            label: 'After action',
            value: o.followupLabel ?? '—',
            caption: props.chart?.categories[2]?.replace('Follow-up ', ''),
            icon: TrendingUp,
            tone: 'neutral',
        },
        {
            label: 'Change from detection',
            value: o.changeLabels[0] ?? '—',
            icon: o.result === 'worsened' ? TrendingDown : TrendingUp,
            tone,
            chip: o.changeLabels[1] ?? undefined,
            chipTone: tone,
        },
    );

    return list;
});

const tiles = computed<Tile[]>(() => {
    if (outcomeTiles.value !== null) {
        return outcomeTiles.value;
    }

    const e = props.latest;
    const breached = e.result === 'breached';
    const pct = e.difference_pct === null ? '—' : `${e.difference_pct}%`;
    const period = `${formatDate(e.period_start)} to ${formatDate(e.period_end)}`;
    const down = e.difference_pct !== null && e.difference_pct.startsWith('-');

    if (e.metric_name === 'weekly_change_pct') {
        return [
            {
                label: 'Observed change',
                value: pct,
                caption: 'Compared with the week before',
                icon: down ? TrendingDown : TrendingUp,
                tone: breached ? 'danger' : 'neutral',
            },
            {
                label: 'Revenue, this week',
                value: formatMoney(e.current_value),
                caption: period,
                icon: Banknote,
                tone: 'neutral',
            },
            {
                label: 'Revenue, week before',
                value: formatMoney(e.comparison_value),
                caption: e.comparison_start
                    ? `${formatDate(e.comparison_start)} to ${formatDate(e.comparison_end)}`
                    : undefined,
                icon: CalendarDays,
                tone: 'neutral',
            },
            {
                label: 'Difference',
                value: formatMoney(e.difference_value),
                caption: `Seen ${props.issue.detection_count} time${props.issue.detection_count === 1 ? '' : 's'}`,
                icon: Gauge,
                tone: breached ? 'danger' : 'neutral',
            },
        ];
    }

    if (e.metric_name === 'target_achievement_pct') {
        return [
            {
                label: 'Target achievement',
                value: pct,
                caption: 'Of the weekly sales target',
                icon: Target,
                tone: breached ? 'danger' : 'neutral',
            },
            {
                label: 'Revenue',
                value: formatMoney(e.current_value),
                caption: period,
                icon: Banknote,
                tone: 'neutral',
            },
            {
                label: 'Weekly target',
                value: formatMoney(e.comparison_value),
                icon: Target,
                tone: 'neutral',
            },
            {
                label: 'Shortfall',
                value: formatMoney(e.difference_value),
                caption: `Seen ${props.issue.detection_count} time${props.issue.detection_count === 1 ? '' : 's'}`,
                icon: Gauge,
                tone: breached ? 'danger' : 'neutral',
            },
        ];
    }

    const missing = e.coverage.missing.length;

    return [
        {
            label: 'Coverage',
            value: pct,
            caption: 'Of the expected branch-days',
            icon: Gauge,
            tone: breached ? 'danger' : 'neutral',
        },
        {
            label: 'Received',
            value:
                e.current_value === null
                    ? '—'
                    : String(parseInt(e.current_value, 10)),
            caption: 'Due branch-days with data',
            icon: Banknote,
            tone: 'neutral',
        },
        {
            label: 'Expected',
            value:
                e.comparison_value === null
                    ? '—'
                    : String(parseInt(e.comparison_value, 10)),
            caption: 'Due branch-days',
            icon: CalendarDays,
            tone: 'neutral',
        },
        {
            label: 'Missing',
            value: String(missing),
            caption: 'Branch-days without data',
            icon: TrendingDown,
            tone: missing > 0 ? 'danger' : 'success',
        },
    ];
});

const ruleIcon = computed<Component>(
    () =>
        ({
            weekly_revenue_decline: TrendingDown,
            sales_target_shortfall: Target,
            missing_sales_data: DatabaseZap,
        })[props.issue.rule_key] ?? Gauge,
);

const headerTone = computed(
    () =>
        ({
            high: 'bg-severity-high-bg text-severity-high',
            medium: 'bg-severity-medium-bg text-severity-medium',
            low: 'bg-muted text-muted-foreground',
        })[props.issue.severity],
);

// One sentence from the latest evaluation, built only from stored figures.
const summarySentence = computed(() => {
    const e = props.latest;

    if (e.difference_pct === null) {
        return props.issue.rule_name;
    }

    const pct = e.difference_pct;

    if (e.metric_name === 'weekly_change_pct') {
        return `Revenue is ${pct.startsWith('-') ? `${pct.slice(1)}% below` : `${pct}% above`} the previous comparable week.`;
    }

    if (e.metric_name === 'target_achievement_pct') {
        return `Revenue reached ${pct}% of the weekly target.`;
    }

    return `Sales data was received for ${pct}% of the expected branch-days.`;
});

const historyItems = computed<TimelineItem[]>(() =>
    props.statusHistory.map((entry, position) => {
        const title =
            entry.from === entry.to
                ? 'Detected again'
                : entry.from === null
                  ? 'First detected'
                  : `${entry.from.replaceAll('_', ' ')} → ${entry.to.replaceAll('_', ' ')}`;
        const tones: Record<string, TimelineItem['tone']> = {
            open: 'danger',
            acknowledged: 'warning',
            action_in_progress: 'info',
            monitoring: 'info',
            resolved: 'success',
        };

        return {
            key: position,
            title: title.charAt(0).toUpperCase() + title.slice(1),
            meta: [formatDateTime(entry.at), triggerLabel(entry.trigger)]
                .filter(Boolean)
                .join(' · '),
            detail: entry.reason ? `Reason: ${entry.reason}` : undefined,
            tone:
                entry.from === entry.to
                    ? 'neutral'
                    : (tones[entry.to] ?? 'neutral'),
        };
    }),
);
</script>

<template>
    <Head :title="issue.title" />

    <header
        class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="flex min-w-0 items-start gap-4">
            <span
                class="flex size-14 shrink-0 items-center justify-center rounded-2xl"
                :class="headerTone"
            >
                <component :is="ruleIcon" class="size-7" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl/8 font-semibold tracking-tight text-foreground"
                    >
                        {{ issue.title }}
                    </h1>
                    <IssueStatusBadge
                        :status="issue.status"
                        :label="issue.status_label"
                    />
                    <SeverityBadge :severity="issue.severity" />
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ summarySentence }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-md border bg-card px-2.5 py-1"
                    >
                        <Building2
                            class="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        {{ issue.department }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-md border bg-card px-2.5 py-1"
                    >
                        <CalendarDays
                            class="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Detected {{ formatDate(issue.first_detected_at) }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-md border bg-card px-2.5 py-1 tabular-nums"
                    >
                        <Hash
                            class="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Issue {{ issue.id }}
                    </span>
                </div>
            </div>
        </div>
        <Button variant="outline" as-child class="shrink-0">
            <Link :href="index().url">
                <ChevronLeft aria-hidden="true" />
                All issues
            </Link>
        </Button>
    </header>

    <div class="space-y-6">
        <Alert v-if="stale" role="status">
            <Info aria-hidden="true" />
            <AlertTitle>The evaluation may be out of date</AlertTitle>
            <AlertDescription class="space-y-2">
                <p v-if="evaluationStatus === 'failed'">
                    The last evaluation run failed for every rule. Figures below
                    come from the last successful evaluation ({{
                        lastEvaluatedAt
                            ? formatDateTime(lastEvaluatedAt)
                            : 'never'
                    }}).
                </p>
                <p v-else-if="evaluationStatus === 'partial'">
                    The last evaluation run could not evaluate every rule, so
                    this issue may not reflect all current conditions.
                </p>
                <p v-else>
                    Sales data or the reporting calendar changed after the
                    latest evaluation ({{
                        lastEvaluatedAt
                            ? formatDateTime(lastEvaluatedAt)
                            : 'never'
                    }}).
                </p>
                <Button
                    size="sm"
                    :disabled="busy"
                    @click="
                        post(refresh().url, {
                            department_id: refreshDepartmentId,
                        })
                    "
                >
                    <RefreshCw aria-hidden="true" />
                    Refresh intelligence
                </Button>
            </AlertDescription>
        </Alert>

        <Alert v-if="latestText" role="status">
            <Info aria-hidden="true" />
            <AlertDescription>
                <span class="font-medium">{{ latestText }}.</span>
                This issue stays {{ issue.status_label.toLowerCase() }} until
                someone resolves it or a verified recovery is recorded.
            </AlertDescription>
        </Alert>

        <!-- The headline figures first. -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricTile
                v-for="tile in tiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :caption="tile.caption"
                :icon="tile.icon"
                :tone="tile.tone"
                :chip="tile.chip"
                :chip-tone="tile.chipTone"
            />
        </div>

        <div
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]"
        >
            <!-- Left: how it unfolded, whether it can be closed, and the evidence. -->
            <div class="min-w-0 space-y-6">
                <Card class="gap-4 p-5">
                    <SectionHeader
                        title="Issue timeline"
                        description="Every status change, in order."
                        :icon="History"
                    />
                    <TimelineList :items="historyItems" />
                </Card>

                <Card v-if="eligibility" class="gap-4 p-5">
                    <SectionHeader
                        title="Resolution eligibility"
                        description="Whether the latest result can close this issue."
                        :icon="ShieldCheck"
                    />
                    <div class="flex flex-wrap items-center gap-4">
                        <span
                            class="flex size-12 shrink-0 items-center justify-center rounded-full"
                            :class="
                                eligibility.met
                                    ? 'bg-success-bg text-success'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <CircleCheck
                                v-if="eligibility.met"
                                class="size-6"
                                aria-hidden="true"
                            />
                            <Minus v-else class="size-6" aria-hidden="true" />
                        </span>
                        <div class="min-w-0 flex-1 basis-64">
                            <p class="text-base/6 font-semibold">
                                {{
                                    eligibility.met
                                        ? 'Eligible for verified resolution'
                                        : 'Not yet eligible'
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                <template v-if="eligibility.met">
                                    The after-action result
                                    {{ eligibility.followupLabel }} meets the
                                    recovery threshold of
                                    {{ eligibility.limitLabel }}.
                                </template>
                                <template v-else>
                                    {{ eligibility.phaseLabel }}.
                                    <template v-if="eligibility.limitLabel">
                                        Recovery needs
                                        {{ eligibility.limitLabel }} or more.
                                    </template>
                                </template>
                            </p>
                        </div>
                        <div
                            v-if="eligibility.limitLabel"
                            class="rounded-xl bg-muted/60 px-4 py-3 text-right"
                        >
                            <p class="text-xs text-muted-foreground">
                                Recovery threshold
                            </p>
                            <p class="text-xl/7 font-semibold tabular-nums">
                                {{ eligibility.limitLabel }}
                            </p>
                        </div>
                    </div>
                    <ul
                        v-if="eligibility.checks.length"
                        class="grid gap-x-6 gap-y-2 border-t pt-4 text-sm sm:grid-cols-2"
                    >
                        <li
                            v-for="check in eligibility.checks"
                            :key="check.name"
                            class="flex items-center gap-2"
                        >
                            <CircleCheck
                                v-if="check.state === 'passed'"
                                class="size-4 shrink-0 text-success"
                                aria-hidden="true"
                            />
                            <CircleX
                                v-else-if="check.state === 'failed'"
                                class="size-4 shrink-0 text-severity-high"
                                aria-hidden="true"
                            />
                            <Minus
                                v-else
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span>{{ check.label }}</span>
                            <span class="sr-only"
                                >({{
                                    check.state === 'passed'
                                        ? 'passed'
                                        : check.state === 'failed'
                                          ? 'failed'
                                          : 'not checked'
                                }})</span
                            >
                        </li>
                    </ul>
                </Card>

                <section class="space-y-3">
                    <SectionHeader
                        title="Supporting evidence"
                        description="Every figure comes from a stored evaluation. Changes are observed, not proven causes."
                        :icon="Gauge"
                    />
                    <Card v-if="chart" class="gap-0 overflow-hidden p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Period</TableHead>
                                    <TableHead class="text-right">{{
                                        chart.metricLabel
                                    }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="(
                                        label, position
                                    ) in chart.categories"
                                    :key="label"
                                    :class="
                                        position === 1
                                            ? 'bg-severity-medium-bg/50'
                                            : position === 2
                                              ? 'bg-success-bg/50'
                                              : ''
                                    "
                                >
                                    <TableCell>{{ label }}</TableCell>
                                    <TableCell
                                        class="text-right font-medium tabular-nums"
                                        >{{
                                            formatMoney(
                                                chart.values[position] ?? null,
                                            )
                                        }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </Card>
                    <div class="grid gap-4 2xl:grid-cols-2">
                        <EvaluationEvidence
                            :evaluation="baseline"
                            heading="When it was first detected"
                        />
                        <EvaluationEvidence
                            v-if="!sameEvaluation"
                            :evaluation="latest"
                            heading="Latest breached evaluation"
                        />
                    </div>
                </section>

                <RelatedMeetingsPanel v-if="meetings" :meetings="meetings" />
            </div>

            <!-- Right: what to do, how it is going and the outcome. -->
            <aside class="min-w-0 space-y-6">
                <Card v-if="anyAction" class="gap-4 p-5">
                    <SectionHeader title="Take action" />
                    <div v-if="actions.acknowledge">
                        <Button
                            class="w-full"
                            :disabled="busy"
                            @click="post(acknowledge(issue.id).url)"
                        >
                            Acknowledge this issue
                        </Button>
                    </div>

                    <form
                        v-if="actions.monitoring"
                        class="grid gap-2"
                        novalidate
                        @submit.prevent="
                            post(monitoring(issue.id).url, {
                                reason: monitoringReason,
                            })
                        "
                    >
                        <Label for="monitoring-reason"
                            >Start monitoring (reason required)</Label
                        >
                        <Textarea
                            id="monitoring-reason"
                            v-model="monitoringReason"
                            rows="2"
                        />
                        <p
                            v-if="errors.reason && errorFor === 'monitoring'"
                            class="text-sm text-severity-high"
                            role="alert"
                        >
                            {{ errors.reason }}
                        </p>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="busy"
                        >
                            Start monitoring
                        </Button>
                    </form>

                    <form
                        v-if="actions.resolve"
                        class="grid gap-2"
                        novalidate
                        @submit.prevent="
                            post(
                                resolve(issue.id).url,
                                { reason: resolveReason },
                                'resolve',
                            )
                        "
                    >
                        <Label for="resolve-reason"
                            >Resolve manually (reason required)</Label
                        >
                        <Textarea
                            id="resolve-reason"
                            v-model="resolveReason"
                            rows="2"
                        />
                        <p
                            v-if="errors.reason && errorFor === 'resolve'"
                            class="text-sm text-severity-high"
                            role="alert"
                        >
                            {{ errors.reason }}
                        </p>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="busy"
                        >
                            Resolve issue
                        </Button>
                    </form>
                </Card>

                <Card v-if="chart" class="gap-3 p-5">
                    <SectionHeader
                        title="Revenue performance"
                        description="Weekly revenue across the compared periods."
                        :icon="TrendingUp"
                    />
                    <OutcomeComparisonChart
                        :categories="chart.categories"
                        :values="chart.values"
                        :unit="chart.unit"
                        :metric-label="chart.metricLabel"
                    />
                </Card>

                <RelatedTasksPanel v-if="tasks" :tasks="tasks" />
                <OutcomePanel :outcomes="outcomes ?? null" />
                <FeedbackPanel v-if="feedback" :feedback="feedback" />

                <Card class="gap-4 p-5">
                    <SectionHeader title="Details" />
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                First detected
                            </dt>
                            <dd class="font-medium">
                                {{ formatDateTime(issue.first_detected_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Last detected
                            </dt>
                            <dd class="font-medium">
                                {{ formatDateTime(issue.last_detected_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Times detected
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ issue.detection_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Action started
                            </dt>
                            <dd class="font-medium">
                                {{ formatDateTime(issue.action_started_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Monitoring started
                            </dt>
                            <dd class="font-medium">
                                {{
                                    formatDateTime(issue.monitoring_started_at)
                                }}
                            </dd>
                        </div>
                        <div v-if="issue.recurrence_of">
                            <dt class="text-xs text-muted-foreground">
                                Recurrence of
                            </dt>
                            <dd class="font-medium">
                                <Link
                                    :href="show(issue.recurrence_of.id).url"
                                    class="text-primary-text underline-offset-2 hover:underline"
                                >
                                    Earlier issue #{{ issue.recurrence_of.id }}
                                </Link>
                            </dd>
                        </div>
                        <div v-if="issue.resolved_at" class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Resolved
                                {{
                                    issue.resolution_type === 'manual'
                                        ? 'manually'
                                        : 'after verified recovery'
                                }}
                                on {{ formatDateTime(issue.resolved_at) }}
                            </dt>
                            <dd
                                v-if="issue.resolution_note"
                                class="font-medium"
                            >
                                {{ issue.resolution_note }}
                            </dd>
                        </div>
                    </dl>
                </Card>

                <TracePanel :trace="trace" />
            </aside>
        </div>
    </div>
</template>
