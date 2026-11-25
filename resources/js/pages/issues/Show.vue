<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Info, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import EvaluationEvidence from '@/components/EvaluationEvidence.vue';
import IssueStatusBadge from '@/components/IssueStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SeverityBadge from '@/components/SeverityBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import RelatedMeetingsPanel from '@/components/issues/RelatedMeetingsPanel.vue';
import RelatedTasksPanel from '@/components/issues/RelatedTasksPanel.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/issues';
import { acknowledge, monitoring, refresh, resolve } from '@/routes/issues';
import type {
    EvaluationView,
    IssueDetail,
    IssueMeetings,
    IssueTasks,
    StatusHistoryEntry,
} from '@/types';

const props = defineProps<{
    issue: IssueDetail;
    baseline: EvaluationView;
    latest: EvaluationView;
    latestOfRule: EvaluationView | null;
    meetings: IssueMeetings | null;
    tasks: IssueTasks | null;
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
            <Link :href="index().url">Back to issues</Link>
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

        <Card class="gap-0 p-0">
            <dl class="grid divide-y text-sm sm:grid-cols-2 sm:divide-y-0">
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">First detected</dt>
                    <dd class="font-medium">
                        {{ formatDateTime(issue.first_detected_at) }}
                    </dd>
                </div>
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">Last detected</dt>
                    <dd class="font-medium">
                        {{ formatDateTime(issue.last_detected_at) }}
                    </dd>
                </div>
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">Times detected</dt>
                    <dd class="font-medium tabular-nums">
                        {{ issue.detection_count }}
                    </dd>
                </div>
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">Action started</dt>
                    <dd class="font-medium">
                        {{ formatDateTime(issue.action_started_at) }}
                    </dd>
                </div>
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">Monitoring started</dt>
                    <dd class="font-medium">
                        {{ formatDateTime(issue.monitoring_started_at) }}
                    </dd>
                </div>
                <div v-if="issue.recurrence_of" class="px-6 py-3">
                    <dt class="text-muted-foreground">Recurrence of</dt>
                    <dd class="font-medium">
                        <Link
                            :href="show(issue.recurrence_of.id).url"
                            class="text-primary-text underline"
                        >
                            Earlier issue #{{ issue.recurrence_of.id }}
                        </Link>
                    </dd>
                </div>
                <div v-if="issue.resolved_at" class="px-6 py-3 sm:col-span-2">
                    <dt class="text-muted-foreground">
                        Resolved
                        {{
                            issue.resolution_type === 'manual'
                                ? 'manually'
                                : 'after verified recovery'
                        }}
                        on {{ formatDateTime(issue.resolved_at) }}
                    </dt>
                    <dd v-if="issue.resolution_note" class="font-medium">
                        {{ issue.resolution_note }}
                    </dd>
                </div>
            </dl>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg/7 font-semibold">Evidence</h2>
            <p class="text-sm text-muted-foreground">
                Every figure comes from a stored evaluation. Changes between
                periods are observed changes; this page makes no claim about
                their cause.
            </p>
            <div class="grid gap-4 lg:grid-cols-2">
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
        <RelatedTasksPanel v-if="tasks" :tasks="tasks" />

        <section v-if="anyAction" class="space-y-3">
            <h2 class="text-lg/7 font-semibold">Actions</h2>
            <Card class="gap-4 p-4">
                <div v-if="actions.acknowledge">
                    <Button
                        :disabled="busy"
                        @click="post(acknowledge(issue.id).url)"
                    >
                        Acknowledge
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
                        class="w-fit"
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
                        class="w-fit"
                        :disabled="busy"
                    >
                        Resolve issue
                    </Button>
                </form>
            </Card>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg/7 font-semibold">Status timeline</h2>
            <ol class="space-y-3 rounded-lg border bg-surface p-4 text-sm">
                <li
                    v-for="(entry, index) in statusHistory"
                    :key="index"
                    class="space-y-0.5"
                >
                    <p class="font-medium">
                        <template v-if="entry.from === entry.to">
                            Detected again
                        </template>
                        <template v-else-if="entry.from === null">
                            First detected
                        </template>
                        <template v-else>
                            {{ entry.from.replaceAll('_', ' ') }} →
                            {{ entry.to.replaceAll('_', ' ') }}
                        </template>
                    </p>
                    <p class="text-muted-foreground">
                        {{ formatDateTime(entry.at) }}
                        <template v-if="triggerLabel(entry.trigger)">
                            · {{ triggerLabel(entry.trigger) }}
                        </template>
                    </p>
                    <p v-if="entry.reason" class="text-foreground">
                        Reason: {{ entry.reason }}
                    </p>
                </li>
            </ol>
        </section>
    </div>
</template>
