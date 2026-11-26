<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import { formatDate, formatMoney, formatTimestamp } from '@/lib/format';
import type { EvaluationView, StatusTone } from '@/types';

const props = defineProps<{ evaluation: EvaluationView; heading: string }>();

const metric = computed(() => {
    switch (props.evaluation.metric_name) {
        case 'weekly_change_pct':
            return {
                label: 'Weekly change',
                current: 'Revenue, this week',
                comparison: 'Revenue, week before',
                money: true,
            };
        case 'target_achievement_pct':
            return {
                label: 'Target achievement',
                current: 'Revenue',
                comparison: 'Weekly target',
                money: true,
            };
        default:
            return {
                label: 'Coverage',
                current: 'Due branch-days received',
                comparison: 'Due branch-days expected',
                money: false,
            };
    }
});

const value = (amount: string | null): string =>
    metric.value.money
        ? formatMoney(amount)
        : amount === null
          ? '—'
          : String(parseInt(amount, 10));

const resultTone = computed<StatusTone>(() => {
    switch (props.evaluation.result) {
        case 'breached':
            return 'danger';
        case 'not_breached':
            return 'success';
        default:
            return 'neutral';
    }
});

const resultLabel = computed(() => {
    const e = props.evaluation;

    return e.result === 'insufficient_data'
        ? `Insufficient data${e.insufficient_reason_text ? ` (${e.insufficient_reason_text})` : ''}`
        : e.result === 'breached'
          ? 'Breached'
          : 'Not breached';
});

const thresholdText = computed(() => {
    const e = props.evaluation;

    if (e.threshold === null) {
        return 'Any missing due branch-day';
    }

    return e.metric_name === 'weekly_change_pct'
        ? `Drop of more than ${e.threshold}%`
        : `Below ${e.threshold}%`;
});

// A coloured edge shows the result at a glance.
const accent = computed(() =>
    props.evaluation.result === 'breached'
        ? 'border-l-severity-high'
        : props.evaluation.result === 'not_breached'
          ? 'border-l-success'
          : 'border-l-slate-300',
);
</script>

<template>
    <Card class="gap-4 border-l-4 p-5" :class="accent">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm/5 font-semibold">{{ heading }}</h3>
            <StatusBadge :label="resultLabel" :tone="resultTone" />
        </div>

        <p class="text-xs text-muted-foreground">
            Evaluated {{ formatTimestamp(evaluation.evaluated_at) }}. Data as of
            {{ formatDate(evaluation.as_of_date) }}, last due date
            {{ formatDate(evaluation.last_due_date) }}.
        </p>

        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs text-muted-foreground">
                    {{
                        evaluation.metric_name === 'weekly_change_pct'
                            ? 'Observed change'
                            : metric.label
                    }}
                </dt>
                <dd class="text-xl/7 font-semibold tabular-nums">
                    {{
                        evaluation.difference_pct === null
                            ? '—'
                            : `${evaluation.difference_pct}%`
                    }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Rule</dt>
                <dd class="font-medium">{{ thresholdText }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">
                    {{ metric.current }}
                </dt>
                <dd class="font-medium tabular-nums">
                    {{ value(evaluation.current_value) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">
                    {{ metric.comparison }}
                </dt>
                <dd class="font-medium tabular-nums">
                    {{ value(evaluation.comparison_value) }}
                </dd>
            </div>
            <div v-if="evaluation.difference_value !== null && metric.money">
                <dt class="text-xs text-muted-foreground">Difference</dt>
                <dd class="font-medium tabular-nums">
                    {{ formatMoney(evaluation.difference_value) }}
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-muted-foreground">Period</dt>
                <dd class="font-medium">
                    {{ formatDate(evaluation.period_start) }} to
                    {{ formatDate(evaluation.period_end) }}
                    <template v-if="evaluation.comparison_start">
                        <span class="font-normal text-muted-foreground">
                            compared with
                        </span>
                        {{ formatDate(evaluation.comparison_start) }} to
                        {{ formatDate(evaluation.comparison_end) }}
                    </template>
                </dd>
            </div>
        </dl>

        <p
            v-if="
                evaluation.metric_name !== 'coverage_pct' &&
                evaluation.coverage.expected_due_branch_days !== null
            "
            class="rounded-md bg-muted/60 px-3 py-2 text-xs text-muted-foreground tabular-nums"
        >
            Branch coverage of the period:
            {{ evaluation.coverage.present_due_branch_days }} of
            {{ evaluation.coverage.expected_due_branch_days }} due branch-days
            ({{ evaluation.coverage.expected_branches.join(', ') }}).
        </p>

        <details v-if="evaluation.coverage.missing.length > 0" class="text-sm">
            <summary
                class="cursor-pointer rounded-md text-primary-text focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                {{ evaluation.coverage.missing.length }} due branch-day(s)
                without data
            </summary>
            <ul class="mt-2 space-y-1 text-muted-foreground">
                <li
                    v-for="gap in evaluation.coverage.missing"
                    :key="gap.branch_code + gap.date"
                >
                    {{ gap.branch_code }}, {{ formatDate(gap.date) }} ({{
                        gap.kind === 'inside_period'
                            ? 'inside the submitted period'
                            : 'after the latest sales date'
                    }})
                </li>
            </ul>
        </details>

        <p class="text-xs text-muted-foreground">
            Source imports:
            {{
                evaluation.import_ids.length > 0
                    ? evaluation.import_ids.map((id) => `#${id}`).join(', ')
                    : 'none'
            }}.
        </p>
    </Card>
</template>
