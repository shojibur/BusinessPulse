<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import ComparabilityChecks from '@/components/outcomes/ComparabilityChecks.vue';
import FeedbackControl from '@/components/outcomes/FeedbackControl.vue';
import OutcomeComparisonChart from '@/components/outcomes/OutcomeComparisonChart.vue';
import OutcomeResultBadge from '@/components/outcomes/OutcomeResultBadge.vue';
import PhaseBadge from '@/components/outcomes/PhaseBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDate, formatDateRange } from '@/lib/format';
import type { OutcomeDetail, OutcomeLink } from '@/types';

defineProps<{
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
        <Card class="gap-3 p-4">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg/7 font-semibold">
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
            <p class="text-sm text-muted-foreground">
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

        <section v-else class="space-y-3" aria-labelledby="values-heading">
            <h2 id="values-heading" class="text-lg/7 font-semibold">
                {{ outcome.outcomeMetricLabel }}
            </h2>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card class="gap-1 p-4"
                    ><dt class="text-xs text-muted-foreground">
                        At detection ({{
                            formatDateRange(
                                outcome.baselinePeriod.start,
                                outcome.baselinePeriod.end,
                            )
                        }})
                    </dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ outcome.values.baselineLabel }}
                    </dd></Card
                >
                <Card class="gap-1 p-4"
                    ><dt class="text-xs text-muted-foreground">
                        Compared period ({{
                            formatDateRange(
                                outcome.followupPeriod.start,
                                outcome.followupPeriod.end,
                            )
                        }})
                    </dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ outcome.values.followupLabel }}
                    </dd></Card
                >
                <Card
                    v-if="
                        outcome.values.referenceLabel && outcome.referencePeriod
                    "
                    class="gap-1 p-4"
                    ><dt class="text-xs text-muted-foreground">
                        Week before the decline ({{
                            formatDateRange(
                                outcome.referencePeriod.start,
                                outcome.referencePeriod.end,
                            )
                        }})
                    </dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ outcome.values.referenceLabel }}
                    </dd></Card
                >
                <Card class="gap-1 p-4"
                    ><dt class="text-xs text-muted-foreground">
                        Change from detection
                    </dt>
                    <dd class="text-xl font-semibold tabular-nums">
                        {{ outcome.values.changeLabels.join(' · ') }}
                    </dd></Card
                >
            </dl>
            <p v-if="outcome.values.recoveredSharePct" class="text-sm">
                Recovered to {{ outcome.values.recoveredSharePct }}% of the
                pre-decline week.
            </p>
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
            <p
                v-if="outcome.weeklyChangeEvidence"
                class="text-xs text-muted-foreground"
            >
                Week-over-week change (rule evidence):
                {{ outcome.weeklyChangeEvidence.baselinePct }}% at detection,
                {{ outcome.weeklyChangeEvidence.followupPct }}% in the compared
                period. It is not an outcome measure.
            </p>
            <Card v-if="chart" class="p-4">
                <OutcomeComparisonChart
                    :categories="chart.categories"
                    :values="chart.values"
                    :unit="chart.unit"
                    :metric-label="chart.metricLabel"
                />
            </Card>
        </section>

        <section class="space-y-3" aria-labelledby="checks-heading">
            <h2 id="checks-heading" class="text-lg/7 font-semibold">
                Comparability checks
            </h2>
            <Card class="p-4"
                ><ComparabilityChecks :checks="outcome.checks"
            /></Card>
            <p
                v-if="outcome.evidence.coverage"
                class="text-xs text-muted-foreground"
            >
                Follow-up week: {{ outcome.evidence.coverage.status }},
                {{ outcome.evidence.coverage.presentBranchDays }} of
                {{ outcome.evidence.coverage.expectedBranchDays }} branch-days
                present. Threshold at detection:
                {{ outcome.evidence.thresholdUsed }}%. Change bands: ±{{
                    outcome.evidence.band
                }}.
            </p>
        </section>

        <Card v-if="outcome.manualNote" class="gap-1 p-4 text-sm">
            <p class="font-medium">Note</p>
            <p class="whitespace-pre-wrap">{{ outcome.manualNote.text }}</p>
            <p class="text-xs text-muted-foreground">
                {{ outcome.manualNote.byName }} ·
                {{ outcome.manualNote.atLabel }}
            </p>
        </Card>

        <Card class="p-4">
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
                    {{ v.userName }}: {{ v.useful ? 'useful' : 'not useful'
                    }}<template v-if="v.note"> — {{ v.note }}</template> ({{
                        v.atLabel
                    }})
                </li>
            </ul>
        </Card>

        <section
            v-if="tasks.length"
            class="space-y-2"
            aria-labelledby="out-tasks-heading"
        >
            <h2 id="out-tasks-heading" class="text-lg/7 font-semibold">
                Linked tasks
            </h2>
            <Card class="gap-0 p-0">
                <ul class="divide-y text-sm">
                    <li
                        v-for="t in tasks"
                        :key="t.id"
                        class="flex flex-wrap justify-between gap-2 px-6 py-3"
                    >
                        <Link
                            v-if="t.url"
                            :href="t.url"
                            class="text-primary-text hover:underline"
                            >{{ t.title }}</Link
                        >
                        <span class="text-muted-foreground"
                            >{{ t.assigneeName }} · {{ t.statusLabel }}</span
                        >
                    </li>
                </ul>
            </Card>
        </section>

        <section
            v-if="statusHistory.length"
            class="space-y-2"
            aria-labelledby="hist-heading"
        >
            <h2 id="hist-heading" class="text-lg/7 font-semibold">
                Issue status changes from outcomes
            </h2>
            <ul class="space-y-1 text-sm">
                <li v-for="(h, i) in statusHistory" :key="i">
                    {{ h.atLabel }}: {{ h.from }} → {{ h.to }} ({{
                        h.byName
                    }})<template v-if="h.reason"> — {{ h.reason }}</template>
                </li>
            </ul>
        </section>

        <section
            v-if="otherOutcomes.length"
            class="space-y-2"
            aria-labelledby="others-heading"
        >
            <h2 id="others-heading" class="text-lg/7 font-semibold">
                Other outcomes for this issue
            </h2>
            <ul class="space-y-1 text-sm">
                <li v-for="o in otherOutcomes" :key="o.id">
                    <Link :href="o.url" class="text-primary-text underline">{{
                        o.evaluatedAtLabel
                    }}</Link>
                    · {{ o.resultLabel }} · {{ o.phaseLabel }}
                </li>
            </ul>
        </section>
    </div>
</template>
