<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    Flag,
    Gauge,
    History,
    Info,
    ListChecks,
    ShieldCheck,
    TrendingUp,
} from '@lucide/vue';
import ComparabilityChecks from '@/components/outcomes/ComparabilityChecks.vue';
import FeedbackControl from '@/components/outcomes/FeedbackControl.vue';
import OutcomeComparisonChart from '@/components/outcomes/OutcomeComparisonChart.vue';
import OutcomeResultBadge from '@/components/outcomes/OutcomeResultBadge.vue';
import PhaseBadge from '@/components/outcomes/PhaseBadge.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import TimelineList from '@/components/TimelineList.vue';
import type { TimelineItem } from '@/components/TimelineList.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { computed } from 'vue';
import { formatDate, formatDateRange } from '@/lib/format';
import type { OutcomeDetail, OutcomeLink } from '@/types';

const props = defineProps<{
    outcome: OutcomeDetail;
    chart: {
        categories: string[];
        values: (string | null)[];
        unit: 'MYR' | '%';
        metricLabel: string;
    } | null;
    otherOutcomes: OutcomeLink[];
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

// The comparison result sets the tone of the headline change tile.
const resultTone = computed<'danger' | 'success' | 'neutral'>(() =>
    props.outcome.result === 'improved'
        ? 'success'
        : props.outcome.result === 'worsened'
          ? 'danger'
          : 'neutral',
);

const historyItems = computed<TimelineItem[]>(() =>
    props.statusHistory.map((h, i) => ({
        key: i,
        title: `${h.from} → ${h.to}`,
        meta: `${h.atLabel} · ${h.byName}`,
        detail: h.reason ? `Reason: ${h.reason}` : undefined,
        tone: 'info' as const,
    })),
);
</script>

<template>
    <Head :title="`Outcome: ${outcome.issue.title}`" />

    <PageHeader
        :title="outcome.heading"
        :description="outcome.standingSentence"
    >
        <Button variant="outline" as-child
            ><Link :href="urls.index">All outcomes</Link></Button
        >
        <Button variant="outline" as-child
            ><Link :href="outcome.issue.url">Open the issue</Link></Button
        >
    </PageHeader>

    <div class="space-y-6">
        <Card
            class="gap-3 border-l-4 p-5"
            :class="{
                'border-l-success': outcome.result === 'improved',
                'border-l-severity-high': outcome.result === 'worsened',
                'border-l-slate-300':
                    outcome.result !== 'improved' &&
                    outcome.result !== 'worsened',
            }"
        >
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base/6 font-semibold">
                    {{ outcome.issue.title }}
                </h2>
                <OutcomeResultBadge
                    :result="outcome.result"
                    :label="outcome.resultLabel"
                />
                <PhaseBadge
                    :label="outcome.phase.label"
                    :used-for-verification="outcome.phase.usedForVerification"
                />
            </div>
            <p class="text-xs text-muted-foreground">
                {{ outcome.departmentName }} · {{ outcome.ruleName }} · issue is
                {{ outcome.issue.statusLabel }} · evaluated
                {{ outcome.evaluatedAtLabel }}
            </p>
            <p v-if="outcome.phase.actionDate" class="text-sm">
                Action started {{ formatDate(outcome.phase.actionDate)
                }}<template v-if="outcome.phase.monitoringDate"
                    >, monitoring since
                    {{ formatDate(outcome.phase.monitoringDate) }}</template
                >. Compared period:
                {{
                    formatDateRange(
                        outcome.followupPeriod.start,
                        outcome.followupPeriod.end,
                    )
                }}.
            </p>
        </Card>

        <Alert v-if="!outcome.comparable" role="status">
            <Info aria-hidden="true" />
            <AlertDescription
                >{{ outcome.notEvaluableText }} No change figure is
                shown.</AlertDescription
            >
        </Alert>

        <section v-else class="space-y-4" aria-labelledby="values-heading">
            <SectionHeader
                id="values-heading"
                :title="outcome.outcomeMetricLabel"
                description="Observed change between the two periods."
                :icon="Gauge"
            />
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricTile
                    label="At detection"
                    :value="outcome.values.baselineLabel ?? '—'"
                    :caption="
                        formatDateRange(
                            outcome.baselinePeriod.start,
                            outcome.baselinePeriod.end,
                        )
                    "
                    :icon="CalendarDays"
                />
                <MetricTile
                    label="Compared period"
                    :value="outcome.values.followupLabel ?? '—'"
                    :caption="
                        formatDateRange(
                            outcome.followupPeriod.start,
                            outcome.followupPeriod.end,
                        )
                    "
                    :icon="CalendarDays"
                />
                <MetricTile
                    v-if="
                        outcome.values.referenceLabel && outcome.referencePeriod
                    "
                    label="Week before the decline"
                    :value="outcome.values.referenceLabel"
                    :caption="
                        formatDateRange(
                            outcome.referencePeriod.start,
                            outcome.referencePeriod.end,
                        )
                    "
                    :icon="Flag"
                />
                <MetricTile
                    label="Change from detection"
                    :value="outcome.values.changeLabels.join(' · ')"
                    :caption="
                        outcome.values.recoveredSharePct
                            ? `Recovered to ${outcome.values.recoveredSharePct}% of the pre-decline week`
                            : undefined
                    "
                    :icon="TrendingUp"
                    :tone="resultTone"
                />
            </div>
            <p class="text-sm">
                {{ outcome.condition.text
                }}<template v-if="outcome.condition.limitLabel">
                    (limit {{ outcome.condition.limitLabel }})</template
                >.
                <span
                    v-if="!outcome.phase.usedForVerification"
                    class="text-muted-foreground"
                >
                    This period is not used for verification, so it did not
                    resolve or reopen the issue.</span
                >
            </p>
            <Card v-if="chart" class="p-5">
                <OutcomeComparisonChart
                    :categories="chart.categories"
                    :values="chart.values"
                    :unit="chart.unit"
                    :metric-label="chart.metricLabel"
                />
            </Card>
            <p
                v-if="outcome.weeklyChangeEvidence"
                class="text-xs text-muted-foreground"
            >
                Week-over-week change (rule evidence):
                {{ outcome.weeklyChangeEvidence.baselinePct }}% at detection,
                {{ outcome.weeklyChangeEvidence.followupPct }}% in the compared
                period. It is not an outcome measure.
            </p>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0 space-y-6">
                <section class="space-y-3" aria-labelledby="checks-heading">
                    <SectionHeader
                        id="checks-heading"
                        title="Comparability checks"
                        description="The comparison is only used when these pass."
                        :icon="ShieldCheck"
                    />
                    <Card class="p-5"
                        ><ComparabilityChecks :checks="outcome.checks"
                    /></Card>
                    <p
                        v-if="outcome.evidence.coverage"
                        class="text-xs text-muted-foreground"
                    >
                        Follow-up week: {{ outcome.evidence.coverage.status }},
                        {{ outcome.evidence.coverage.presentBranchDays }} of
                        {{ outcome.evidence.coverage.expectedBranchDays }}
                        branch-days present. Threshold at detection:
                        {{ outcome.evidence.thresholdUsed }}%. Change bands: ±{{
                            outcome.evidence.band
                        }}.
                    </p>
                </section>

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
                                <span class="text-xs text-muted-foreground"
                                    >{{ t.assigneeName }} ·
                                    {{ t.statusLabel }}</span
                                >
                            </li>
                        </ul>
                    </Card>
                </section>
            </div>

            <aside class="min-w-0 space-y-6">
                <Card v-if="outcome.manualNote" class="gap-1 p-5 text-sm">
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
                    <ul
                        v-if="feedback.votes?.length"
                        class="mt-3 space-y-1 text-xs text-muted-foreground"
                    >
                        <li v-for="(v, i) in feedback.votes" :key="i">
                            {{ v.userName }}:
                            {{ v.useful ? 'useful' : 'not useful'
                            }}<template v-if="v.note"> — {{ v.note }}</template>
                            ({{ v.atLabel }})
                        </li>
                    </ul>
                </Card>

                <Card
                    v-if="statusHistory.length"
                    class="gap-4 p-5"
                    aria-labelledby="hist-heading"
                >
                    <SectionHeader
                        id="hist-heading"
                        title="Issue status changes"
                        :icon="History"
                    />
                    <TimelineList :items="historyItems" />
                </Card>

                <Card
                    v-if="otherOutcomes.length"
                    class="gap-3 p-5"
                    aria-labelledby="others-heading"
                >
                    <SectionHeader
                        id="others-heading"
                        title="Other outcomes"
                        description="For the same issue."
                    />
                    <ul class="space-y-2 text-sm">
                        <li v-for="o in otherOutcomes" :key="o.id">
                            <Link
                                :href="o.url"
                                class="text-primary-text underline-offset-2 hover:underline"
                                >{{ o.evaluatedAtLabel }}</Link
                            >
                            <span class="text-xs text-muted-foreground">
                                · {{ o.resultLabel }} · {{ o.phaseLabel }}</span
                            >
                        </li>
                    </ul>
                </Card>
            </aside>
        </div>
    </div>
</template>
