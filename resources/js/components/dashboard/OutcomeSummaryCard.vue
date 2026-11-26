<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck, Info, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import { index as outcomesIndex } from '@/routes/outcomes';
import type { StatusTone } from '@/types';
import type { DashboardOutcome } from './types';

const props = defineProps<{ outcome: DashboardOutcome | null }>();

const tone = computed<StatusTone>(() => {
    switch (props.outcome?.result) {
        case 'improved':
            return 'success';
        case 'worsened':
            return 'danger';
        case 'not_evaluable':
            return 'neutral';
        default:
            return 'info';
    }
});
</script>

<template>
    <Card class="gap-4 p-5">
        <SectionHeader
            title="Outcome verification"
            description="What changed after actions were taken."
            :icon="TrendingUp"
        >
            <Link
                :href="outcomesIndex().url"
                class="text-sm font-medium text-primary-text hover:underline"
                >View outcomes</Link
            >
        </SectionHeader>

        <EmptyState
            v-if="outcome === null"
            :icon="TrendingUp"
            message="No outcome has been recorded yet. It appears after actions are taken and a later week is closed."
        />

        <template v-else>
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :label="outcome.resultLabel" :tone="tone" />
                <StatusBadge :label="outcome.phaseLabel" tone="neutral" />
            </div>

            <Link
                v-if="outcome.issueUrl"
                :href="outcome.issueUrl"
                class="text-sm/5 font-medium text-foreground hover:underline"
                >{{ outcome.issueTitle }}</Link
            >

            <p
                v-if="outcome.notEvaluableText"
                class="text-sm text-muted-foreground"
            >
                {{ outcome.notEvaluableText }}
            </p>
            <div v-else class="space-y-2 text-sm">
                <p class="text-muted-foreground">
                    {{ outcome.outcomeMetricLabel }}:
                    <span class="font-medium text-foreground tabular-nums"
                        >{{ outcome.baselineLabel }} →
                        {{ outcome.followupLabel }}</span
                    >
                </p>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="label in outcome.changeLabels"
                        :key="label"
                        class="rounded-md bg-muted px-2 py-0.5 text-xs font-semibold tabular-nums"
                        >{{ label }}</span
                    >
                </div>
            </div>

            <p
                v-if="
                    outcome.resolutionConditionMet &&
                    outcome.usedForVerification
                "
                class="flex items-center gap-2 rounded-lg bg-success-bg px-3 py-2 text-sm font-medium text-success"
            >
                <CircleCheck class="size-4 shrink-0" aria-hidden="true" />
                Meets the recovery condition
            </p>
            <p
                v-else
                class="flex items-center gap-2 text-xs text-muted-foreground"
            >
                <Info class="size-4 shrink-0" aria-hidden="true" />
                Observed change, not proof of cause. Evaluated
                {{ outcome.evaluatedAtLabel }}.
            </p>
            <Link
                :href="outcome.url"
                class="w-fit text-sm font-medium text-primary-text hover:underline"
                >Open the comparison</Link
            >
        </template>
    </Card>
</template>
