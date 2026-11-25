<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDate, formatDateRange } from '@/lib/format';
import type { StatusTone, WeekStateView } from '@/types';

defineProps<{ weeks: WeekStateView[] }>();

function describe(week: WeekStateView): { label: string; tone: StatusTone } {
    if (week.status === 'open') {
        return { label: 'Open week (informational)', tone: 'neutral' };
    }

    switch (week.completeness) {
        case 'complete':
            return { label: 'Closed and complete', tone: 'success' };
        case 'incomplete':
            return { label: 'Closed but incomplete', tone: 'warning' };
        default:
            return { label: 'Closed, no data', tone: 'neutral' };
    }
}
</script>

<template>
    <ul class="divide-y rounded-lg border bg-surface">
        <li v-for="week in weeks" :key="week.week_start" class="space-y-2 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium">
                    Week of
                    {{ formatDateRange(week.week_start, week.week_end) }}
                </p>
                <StatusBadge
                    :label="describe(week).label"
                    :tone="describe(week).tone"
                />
            </div>

            <p class="text-sm text-muted-foreground tabular-nums">
                {{ week.present_branch_days }} of
                {{ week.expected_branch_days }} expected branch-days present
                <template v-if="week.coverage_pct !== null">
                    ({{ Number(week.coverage_pct).toFixed(1) }}%)
                </template>
            </p>

            <p
                v-if="week.status === 'open'"
                class="text-xs text-muted-foreground"
            >
                This week is still open. Days that are not yet due are not
                counted as missing.
            </p>

            <details v-if="week.gaps.length > 0" class="text-sm">
                <summary
                    class="cursor-pointer rounded-md text-primary-text focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    {{ week.gaps.length + week.more_gaps }} due branch-day(s)
                    without data
                </summary>
                <ul class="mt-2 space-y-1 text-muted-foreground">
                    <li
                        v-for="gap in week.gaps"
                        :key="gap.branch_code + gap.date"
                    >
                        {{ gap.branch_code }}, {{ formatDate(gap.date) }}
                    </li>
                    <li v-if="week.more_gaps > 0">
                        and {{ week.more_gaps }} more
                    </li>
                </ul>
            </details>
        </li>
    </ul>
</template>
