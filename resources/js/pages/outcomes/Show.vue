<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleCheck,
    Gauge,
    History,
    Info,
    ListChecks,
    Minus,
    Route,
    ShieldCheck,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import TraceTimeline from '@/components/activity/TraceTimeline.vue';
import ComparabilityChecks from '@/components/outcomes/ComparabilityChecks.vue';
import FeedbackControl from '@/components/outcomes/FeedbackControl.vue';
import OutcomeComparisonChart from '@/components/outcomes/OutcomeComparisonChart.vue';
import OutcomeResultBadge from '@/components/outcomes/OutcomeResultBadge.vue';
import PhaseBadge from '@/components/outcomes/PhaseBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import StepTracker from '@/components/StepTracker.vue';
import type { Step } from '@/components/StepTracker.vue';
import TimelineList from '@/components/TimelineList.vue';
import type { TimelineItem } from '@/components/TimelineList.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { computed } from 'vue';
import { formatDate, formatDateRange } from '@/lib/format';
import type { OutcomeDetail, OutcomeLink, TracePanelData } from '@/types';

const props = defineProps<{
    outcome: OutcomeDetail;
    chart: {
        categories: string[];
        values: (string | null)[];
        unit: 'MYR' | '%';
        metricLabel: string;
    } | null;
    otherOutcomes: OutcomeLink[];
    trace?: TracePanelData | null;
    tasks: {
        id: number;
        title: string;
        assigneeName: string | null;
        dueDate: string | null;
        statusLabel: string;
        url: string | null;
    }[];
    statusHistory: {
        at: string;
        atLabel: string;
        from: string | null;
        to: string;
        byName: string;
        reason: string | null;
    }[];
    feedback: {
        mine: boolean | null;
        counts: { useful: number; notUseful: number };
        votes:
            | {
                  userName: string;
                  useful: boolean;
                  note: string | null;
                  atLabel: string;
              }[]
            | null;
    };
    urls: { saveNote: string | null; submitFeedback: string; index: string };
}>();

const historyItems = computed<TimelineItem[]>(() =>
    props.statusHistory.map((h, i) => ({
        key: i,
        title: `${h.from} → ${h.to}`,
        meta: `${h.atLabel} · ${h.byName}`,
        detail: h.reason ? `Reason: ${h.reason}` : undefined,
        tone: 'info' as const,
    })),
);

// Where the issue is in its life: a tracker from Open to Resolved, all done once resolved.
const issueSteps: Step[] = [
    { key: 'open', label: 'Open' },
    { key: 'acknowledged', label: 'Acknowledged' },
    { key: 'action_in_progress', label: 'Action in progress' },
    { key: 'monitoring', label: 'Monitoring' },
    { key: 'resolved', label: 'Resolved' },
];

const statusIndex = computed(() =>
    props.outcome.issue.status === 'resolved'
        ? issueSteps.length
        : Math.max(
              0,
              issueSteps.findIndex((s) => s.key === props.outcome.issue.status),
          ),
);

const headerIcon = computed(() =>
    props.outcome.result === 'improved'
        ? CircleCheck
        : props.outcome.result === 'worsened'
          ? TrendingDown
          : Minus,
);

const headerTone = computed(() =>
    props.outcome.result === 'improved'
        ? 'bg-success-bg text-success'
        : props.outcome.result === 'worsened'
          ? 'bg-severity-high-bg text-severity-high'
          : 'bg-muted text-muted-foreground',
);

const changeTone = computed(() =>
    props.outcome.result === 'improved'
        ? 'text-success'
        : props.outcome.result === 'worsened'
          ? 'text-severity-high'
          : 'text-foreground',
);

// The weeks that were compared, with the week-over-week change the rule recorded (never computed here).
const periodCards = computed(() => {
    const o = props.outcome;
    const cards: {
        key: string;
        title: string;
        range: string;
        value: string | null;
        chip: string | null;
        chipTone: string;
        phase: string;
        tint: string;
    }[] = [];

    if (o.referencePeriod && o.values.referenceLabel) {
        cards.push({
            key: 'reference',
            title: 'Before the decline',
            range: formatDateRange(
                o.referencePeriod.start,
                o.referencePeriod.end,
            ),
            value: o.values.referenceLabel,
            chip: null,
            chipTone: '',
            phase: 'Comparison week',
            tint: 'border-border bg-muted/40',
        });
    }

    cards.push({
        key: 'baseline',
        title: 'At detection',
        range: formatDateRange(o.baselinePeriod.start, o.baselinePeriod.end),
        value: o.values.baselineLabel,
        chip: o.weeklyChangeEvidence
            ? `${o.weeklyChangeEvidence.baselinePct}%`
            : null,
        chipTone: 'bg-severity-high-bg text-severity-high',
        phase: 'Issue baseline',
        tint: 'border-amber-200 bg-severity-medium-bg/40',
    });

    cards.push({
        key: 'followup',
        title: 'Compared period',
        range: formatDateRange(o.followupPeriod.start, o.followupPeriod.end),
        value: o.values.followupLabel,
        chip: o.weeklyChangeEvidence
            ? `${o.weeklyChangeEvidence.followupPct}%`
            : null,
        chipTone: 'bg-success-bg text-success',
        phase: `${o.phase.label}${o.phase.usedForVerification ? ' (used for verification)' : ' (not used for verification)'}`,
        tint: o.phase.usedForVerification
            ? 'border-teal-300 bg-teal-50'
            : 'border-border bg-muted/40',
    });

    return cards;
});
</script>

<template>
    <Head :title="`Outcome: ${outcome.issue.title}`" />

    <PageHeader
        title="Outcome verification"
        description="The business result after management actions, with the evidence behind it."
    >
        <Button variant="outline" as-child
            ><Link :href="urls.index">All outcomes</Link></Button
        >
        <Button variant="outline" as-child
            ><Link :href="outcome.issue.url">Open the issue</Link></Button
        >
    </PageHeader>

    <div class="space-y-6">
        <Card class="gap-6 p-5">
            <div class="flex flex-wrap items-center gap-4">
                <span
                    class="flex size-14 shrink-0 items-center justify-center rounded-2xl"
                    :class="headerTone"
                >
                    <component
                        :is="headerIcon"
                        class="size-7"
                        aria-hidden="true"
                    />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg/7 font-semibold tracking-tight">
                        {{ outcome.issue.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ outcome.departmentName }} · {{ outcome.ruleName }} ·
                        Detected {{ outcome.issue.detectedLabel }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <OutcomeResultBadge
                        :result="outcome.result"
                        :label="outcome.resultLabel"
                    />
                    <PhaseBadge
                        :label="outcome.phase.label"
                        :used-for-verification="
                            outcome.phase.usedForVerification
                        "
                    />
                </div>
            </div>
            <StepTracker :steps="issueSteps" :current="statusIndex" />
        </Card>

        <Alert v-if="!outcome.comparable" role="status">
            <Info aria-hidden="true" />
            <AlertDescription
                >{{ outcome.notEvaluableText }} No change figure is
                shown.</AlertDescription
            >
        </Alert>

        <Tabs default-value="outcome">
            <TabsList class="max-w-full overflow-x-auto">
                <TabsTrigger value="outcome">Outcome</TabsTrigger>
                <TabsTrigger value="trace">Activity trace</TabsTrigger>
                <TabsTrigger value="feedback">Feedback</TabsTrigger>
                <TabsTrigger value="history">History</TabsTrigger>
            </TabsList>

            <TabsContent value="outcome" class="mt-6">
                <div
                    class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
                >
                    <div class="min-w-0 space-y-6">
                        <Card v-if="outcome.comparable" class="gap-5 p-5">
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-full"
                                    :class="headerTone"
                                >
                                    <component
                                        :is="headerIcon"
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h2 class="text-base/6 font-semibold">
                                        {{ outcome.outcomeMetricLabel }}
                                    </h2>
                                    <p class="text-xs text-muted-foreground">
                                        {{ outcome.condition.text
                                        }}<template
                                            v-if="outcome.condition.limitLabel"
                                        >
                                            (limit
                                            {{
                                                outcome.condition.limitLabel
                                            }})</template
                                        >
                                    </p>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Evaluated {{ outcome.evaluatedAtLabel }}
                                </p>
                            </div>
                            <dl
                                class="grid divide-y rounded-xl border sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4"
                            >
                                <div class="p-4">
                                    <dt class="text-xs text-muted-foreground">
                                        At detection
                                    </dt>
                                    <dd
                                        class="text-xl/7 font-semibold tabular-nums"
                                    >
                                        {{
                                            outcome.values.baselineLabel ?? '—'
                                        }}
                                    </dd>
                                    <dd class="text-xs text-muted-foreground">
                                        {{
                                            formatDateRange(
                                                outcome.baselinePeriod.start,
                                                outcome.baselinePeriod.end,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div class="p-4">
                                    <dt class="text-xs text-muted-foreground">
                                        Compared period
                                    </dt>
                                    <dd
                                        class="text-xl/7 font-semibold tabular-nums"
                                    >
                                        {{
                                            outcome.values.followupLabel ?? '—'
                                        }}
                                    </dd>
                                    <dd class="text-xs text-muted-foreground">
                                        {{
                                            formatDateRange(
                                                outcome.followupPeriod.start,
                                                outcome.followupPeriod.end,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div
                                    v-for="(label, index) in outcome.values
                                        .changeLabels"
                                    :key="label"
                                    class="p-4"
                                >
                                    <dt class="text-xs text-muted-foreground">
                                        {{
                                            outcome.values.changeLabels.length >
                                            1
                                                ? index === 0
                                                    ? 'Absolute change'
                                                    : 'Relative change'
                                                : 'Change from detection'
                                        }}
                                    </dt>
                                    <dd
                                        class="text-xl/7 font-semibold tabular-nums"
                                        :class="changeTone"
                                    >
                                        {{ label }}
                                    </dd>
                                    <dd
                                        v-if="
                                            index === 0 &&
                                            outcome.values.recoveredSharePct
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ outcome.values.recoveredSharePct }}%
                                        of the pre-decline week
                                    </dd>
                                </div>
                            </dl>
                            <p
                                v-if="!outcome.phase.usedForVerification"
                                class="text-xs text-muted-foreground"
                            >
                                This period is not used for verification, so it
                                did not resolve or reopen the issue.
                            </p>
                        </Card>

                        <div
                            v-if="outcome.comparable"
                            class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]"
                        >
                            <Card v-if="chart" class="gap-4 p-5">
                                <SectionHeader
                                    :title="`${chart.metricLabel} trend`"
                                    description="The compared periods, coloured by phase."
                                    :icon="TrendingUp"
                                />
                                <OutcomeComparisonChart
                                    :categories="chart.categories"
                                    :values="chart.values"
                                    :unit="chart.unit"
                                    :metric-label="chart.metricLabel"
                                    :phase="outcome.phase.code"
                                />
                            </Card>

                            <Card class="gap-4 p-5">
                                <SectionHeader
                                    title="Key metrics"
                                    :icon="Gauge"
                                />
                                <dl class="divide-y text-sm">
                                    <div
                                        v-if="outcome.values.referenceLabel"
                                        class="flex items-center justify-between gap-3 py-2.5 first:pt-0"
                                    >
                                        <dt class="text-muted-foreground">
                                            Week before the decline
                                        </dt>
                                        <dd class="font-medium tabular-nums">
                                            {{ outcome.values.referenceLabel }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="outcome.condition.limitLabel"
                                        class="flex items-center justify-between gap-3 py-2.5"
                                    >
                                        <dt class="text-muted-foreground">
                                            Recovery threshold
                                        </dt>
                                        <dd class="font-medium tabular-nums">
                                            {{ outcome.condition.limitLabel }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex items-center justify-between gap-3 py-2.5"
                                    >
                                        <dt class="text-muted-foreground">
                                            Compared period
                                        </dt>
                                        <dd class="font-medium tabular-nums">
                                            {{
                                                outcome.values.followupLabel ??
                                                '—'
                                            }}
                                        </dd>
                                    </div>
                                    <div
                                        class="flex items-center justify-between gap-3 py-2.5"
                                    >
                                        <dt class="text-muted-foreground">
                                            Outcome
                                        </dt>
                                        <dd>
                                            <OutcomeResultBadge
                                                :result="outcome.result"
                                                :label="outcome.resultLabel"
                                            />
                                        </dd>
                                    </div>
                                    <div
                                        class="flex items-center justify-between gap-3 py-2.5 last:pb-0"
                                    >
                                        <dt class="text-muted-foreground">
                                            Threshold met
                                        </dt>
                                        <dd>
                                            <StatusBadge
                                                :label="
                                                    outcome.condition.met
                                                        ? 'Yes'
                                                        : 'No'
                                                "
                                                :tone="
                                                    outcome.condition.met
                                                        ? 'success'
                                                        : 'neutral'
                                                "
                                            />
                                        </dd>
                                    </div>
                                </dl>
                            </Card>
                        </div>

                        <section
                            v-if="outcome.comparable"
                            class="space-y-3"
                            aria-labelledby="periods-heading"
                        >
                            <SectionHeader
                                id="periods-heading"
                                title="Reporting periods"
                                description="The weeks that were compared."
                                :icon="CalendarDays"
                            />
                            <div
                                class="grid gap-4 sm:grid-cols-2"
                                :class="
                                    periodCards.length === 3
                                        ? 'lg:grid-cols-3'
                                        : ''
                                "
                            >
                                <div
                                    v-for="card in periodCards"
                                    :key="card.key"
                                    class="rounded-xl border p-4"
                                    :class="card.tint"
                                >
                                    <p
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        {{ card.title }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ card.range }}
                                    </p>
                                    <p
                                        class="mt-2 text-lg/7 font-semibold tabular-nums"
                                    >
                                        {{ card.value ?? '—' }}
                                    </p>
                                    <span
                                        v-if="card.chip"
                                        class="mt-1 inline-flex rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums"
                                        :class="card.chipTone"
                                        >{{ card.chip }}
                                        <span class="ms-1 font-normal"
                                            >vs the week before</span
                                        ></span
                                    >
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        {{ card.phase }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <Card class="gap-4 p-5">
                            <SectionHeader
                                title="Classification and resolution"
                                :icon="ShieldCheck"
                            />
                            <dl
                                class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <dt class="text-muted-foreground">
                                        Outcome classification
                                    </dt>
                                    <dd>
                                        <OutcomeResultBadge
                                            :result="outcome.result"
                                            :label="outcome.resultLabel"
                                        />
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <dt class="text-muted-foreground">Phase</dt>
                                    <dd class="font-medium">
                                        {{ outcome.phase.label }}
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <dt class="text-muted-foreground">
                                        Used for verification
                                    </dt>
                                    <dd>
                                        <StatusBadge
                                            :label="
                                                outcome.phase
                                                    .usedForVerification
                                                    ? 'Yes'
                                                    : 'No'
                                            "
                                            :tone="
                                                outcome.phase
                                                    .usedForVerification
                                                    ? 'success'
                                                    : 'neutral'
                                            "
                                        />
                                    </dd>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <dt class="text-muted-foreground">
                                        Issue status
                                    </dt>
                                    <dd class="font-medium">
                                        {{ outcome.issue.statusLabel }}
                                    </dd>
                                </div>
                                <div
                                    v-if="outcome.phase.actionDate"
                                    class="flex items-center justify-between gap-3 sm:col-span-2"
                                >
                                    <dt class="text-muted-foreground">
                                        Timeline
                                    </dt>
                                    <dd class="text-right font-medium">
                                        Action started
                                        {{ formatDate(outcome.phase.actionDate)
                                        }}<template
                                            v-if="outcome.phase.monitoringDate"
                                            >, monitoring since
                                            {{
                                                formatDate(
                                                    outcome.phase
                                                        .monitoringDate,
                                                )
                                            }}</template
                                        >
                                    </dd>
                                </div>
                            </dl>
                        </Card>

                        <section
                            v-if="tasks.length"
                            class="space-y-3"
                            aria-labelledby="out-tasks-heading"
                        >
                            <SectionHeader
                                id="out-tasks-heading"
                                title="Linked tasks"
                                :icon="ListChecks"
                            />
                            <Card class="gap-0 overflow-hidden p-0">
                                <ul class="divide-y text-sm">
                                    <li
                                        v-for="t in tasks"
                                        :key="t.id"
                                        class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5"
                                    >
                                        <Link
                                            v-if="t.url"
                                            :href="t.url"
                                            class="font-medium text-primary-text hover:underline"
                                            >{{ t.title }}</Link
                                        >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{ t.assigneeName }} ·
                                            {{ t.statusLabel }}</span
                                        >
                                    </li>
                                </ul>
                            </Card>
                        </section>
                    </div>

                    <aside class="min-w-0 space-y-6">
                        <Card class="gap-4 p-5">
                            <SectionHeader
                                title="Comparability assessment"
                                description="The comparison is only used when these pass."
                                :icon="ShieldCheck"
                            />
                            <ComparabilityChecks :checks="outcome.checks" />
                            <p
                                v-if="outcome.evidence.coverage"
                                class="text-xs text-muted-foreground"
                            >
                                Follow-up week:
                                {{ outcome.evidence.coverage.status }},
                                {{
                                    outcome.evidence.coverage.presentBranchDays
                                }}
                                of
                                {{
                                    outcome.evidence.coverage.expectedBranchDays
                                }}
                                branch-days present. Threshold at detection:
                                {{ outcome.evidence.thresholdUsed }}%. Change
                                bands: ±{{ outcome.evidence.band }}.
                            </p>
                        </Card>

                        <div
                            class="flex gap-3 rounded-xl border border-info/20 bg-info-bg/60 p-4 text-sm"
                            role="note"
                        >
                            <Info
                                class="mt-0.5 size-5 shrink-0 text-info"
                                aria-hidden="true"
                            />
                            <div class="space-y-1">
                                <p class="font-semibold text-foreground">
                                    Interpretation
                                </p>
                                <p v-if="outcome.comparable">
                                    {{ outcome.condition.text
                                    }}<template
                                        v-if="outcome.condition.limitLabel"
                                    >
                                        (limit
                                        {{
                                            outcome.condition.limitLabel
                                        }})</template
                                    >.
                                </p>
                                <p v-else>{{ outcome.notEvaluableText }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ outcome.standingSentence }}
                                </p>
                            </div>
                        </div>

                        <Card
                            v-if="outcome.manualNote"
                            class="gap-1 p-5 text-sm"
                        >
                            <SectionHeader title="Note" />
                            <p class="whitespace-pre-wrap">
                                {{ outcome.manualNote.text }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ outcome.manualNote.byName }} ·
                                {{ outcome.manualNote.atLabel }}
                            </p>
                        </Card>

                        <Card class="p-5">
                            <FeedbackControl
                                :submit-url="urls.submitFeedback"
                                :mine="feedback.mine"
                                :counts="feedback.counts"
                                label="Was this outcome useful?"
                            />
                        </Card>

                        <p
                            v-if="outcome.weeklyChangeEvidence"
                            class="px-1 text-xs text-muted-foreground"
                        >
                            Week-over-week change (rule evidence):
                            {{ outcome.weeklyChangeEvidence.baselinePct }}% at
                            detection,
                            {{ outcome.weeklyChangeEvidence.followupPct }}% in
                            the compared period. It is not an outcome measure.
                        </p>
                    </aside>
                </div>
            </TabsContent>

            <TabsContent value="trace" class="mt-6">
                <Card class="gap-4 p-5">
                    <SectionHeader
                        title="Activity trace"
                        description="From imported data to the outcome."
                        :icon="Route"
                    />
                    <Deferred data="trace">
                        <template #fallback
                            ><Skeleton class="h-32 w-full"
                        /></template>
                        <TraceTimeline v-if="trace" :trace="trace" />
                    </Deferred>
                </Card>
            </TabsContent>

            <TabsContent value="feedback" class="mt-6">
                <Card class="max-w-2xl gap-4 p-5">
                    <SectionHeader title="Feedback" />
                    <FeedbackControl
                        :submit-url="urls.submitFeedback"
                        :mine="feedback.mine"
                        :counts="feedback.counts"
                        label="Was this outcome useful?"
                    />
                    <ul
                        v-if="feedback.votes?.length"
                        class="space-y-2 border-t pt-3 text-sm"
                    >
                        <li v-for="(v, i) in feedback.votes" :key="i">
                            <span class="font-medium">{{ v.userName }}</span>
                            <span class="text-muted-foreground">
                                · {{ v.useful ? 'useful' : 'not useful'
                                }}<template v-if="v.note">
                                    — {{ v.note }}</template
                                >
                                · {{ v.atLabel }}</span
                            >
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No feedback has been recorded yet.
                    </p>
                </Card>
            </TabsContent>

            <TabsContent value="history" class="mt-6">
                <div class="grid items-start gap-6 lg:grid-cols-2">
                    <Card class="gap-4 p-5">
                        <SectionHeader
                            title="Issue status changes"
                            :icon="History"
                        />
                        <TimelineList
                            v-if="statusHistory.length"
                            :items="historyItems"
                        />
                        <p v-else class="text-sm text-muted-foreground">
                            No status changes came from outcomes yet.
                        </p>
                    </Card>
                    <Card class="gap-3 p-5">
                        <SectionHeader
                            title="Other outcomes"
                            description="For the same issue."
                        />
                        <ul
                            v-if="otherOutcomes.length"
                            class="space-y-2 text-sm"
                        >
                            <li v-for="o in otherOutcomes" :key="o.id">
                                <Link
                                    :href="o.url"
                                    class="text-primary-text underline-offset-2 hover:underline"
                                    >{{ o.evaluatedAtLabel }}</Link
                                >
                                <span class="text-xs text-muted-foreground">
                                    · {{ o.resultLabel }} ·
                                    {{ o.phaseLabel }}</span
                                >
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            This is the only outcome for the issue so far.
                        </p>
                    </Card>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
