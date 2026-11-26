<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    ChevronLeft,
    Gauge,
    History,
    Info,
    RefreshCw,
    Target,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EvaluationEvidence from '@/components/EvaluationEvidence.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { index, show } from '@/routes/issues';
import { acknowledge, monitoring, refresh, resolve } from '@/routes/issues';
import type {
    EvaluationView,
    IssueDetail,
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
    icon: typeof Gauge;
    tone: 'neutral' | 'danger' | 'success' | 'warning' | 'info';
};

const tiles = computed<Tile[]>(() => {
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

    <PageHeader
        :title="issue.title"
        :description="`${issue.department} · ${issue.rule_name}`"
    >
        <SeverityBadge :severity="issue.severity" />
        <IssueStatusBadge :status="issue.status" :label="issue.status_label" />
        <Button variant="outline" as-child>
            <Link :href="index().url">
                <ChevronLeft aria-hidden="true" />
                All issues
            </Link>
        </Button>
    </PageHeader>

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
            />
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <!-- Main column: the evidence and the work. -->
            <div class="min-w-0 space-y-6">
                <section class="space-y-3">
                    <SectionHeader
                        title="Evidence"
                        description="Every figure comes from a stored evaluation. Changes are observed, not proven causes."
                        :icon="Gauge"
                    />
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

                <RelatedTasksPanel v-if="tasks" :tasks="tasks" />
                <RelatedMeetingsPanel v-if="meetings" :meetings="meetings" />
                <OutcomePanel :outcomes="outcomes ?? null" />
            </div>

            <!-- Side column: what to do, the facts and the history. -->
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

                <FeedbackPanel v-if="feedback" :feedback="feedback" />

                <Card class="gap-4 p-5">
                    <SectionHeader title="Status history" :icon="History" />
                    <TimelineList :items="historyItems" />
                </Card>

                <TracePanel :trace="trace" />
            </aside>
        </div>
    </div>
</template>
