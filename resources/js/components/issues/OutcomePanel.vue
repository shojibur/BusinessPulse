<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import { TrendingUp } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import ChangeFigures from '@/components/outcomes/ChangeFigures.vue';
import OutcomeResultBadge from '@/components/outcomes/OutcomeResultBadge.vue';
import PhaseBadge from '@/components/outcomes/PhaseBadge.vue';
import { Card } from '@/components/ui/card';
import type { OutcomePanelData } from '@/types';

defineProps<{ outcomes: OutcomePanelData | null }>();
</script>

<template>
    <section class="space-y-3" aria-labelledby="outcome-heading">
        <SectionHeader
            id="outcome-heading"
            :title="outcomes?.heading ?? 'Observed change after actions'"
            description="What changed after actions were taken. Observed change, not proof of cause."
            :icon="TrendingUp"
        />
        <Card v-if="outcomes === null" class="gap-1 px-6 py-8 text-center">
            <TrendingUp
                class="mx-auto size-6 text-muted-foreground"
                aria-hidden="true"
            />
            <p class="text-sm text-muted-foreground">
                No outcome has been recorded for this issue yet.
            </p>
        </Card>
        <Card v-else class="gap-3 p-5 text-sm">
            <div class="flex flex-wrap items-center gap-2">
                <OutcomeResultBadge
                    :result="outcomes.latest.result"
                    :label="outcomes.latest.resultLabel"
                />
                <PhaseBadge
                    :label="outcomes.latest.phaseLabel"
                    :used-for-verification="outcomes.latest.usedForVerification"
                />
            </div>
            <p v-if="outcomes.latest.notEvaluableText">
                {{ outcomes.latest.notEvaluableText }}
            </p>
            <p v-else>
                {{ outcomes.latest.outcomeMetricLabel }}:
                {{ outcomes.latest.baselineLabel }} at detection,
                {{ outcomes.latest.followupLabel }} in the compared period.
                <ChangeFigures :labels="outcomes.latest.changeLabels" />
            </p>
            <p class="text-xs text-muted-foreground">
                Evaluated {{ outcomes.latest.evaluatedAtLabel }}
            </p>
            <Link
                :href="outcomes.latest.url"
                class="w-fit text-primary-text underline-offset-2 hover:underline"
                >Open the comparison</Link
            >
            <ul
                v-if="outcomes.earlier.length"
                class="space-y-1 border-t pt-3 text-xs text-muted-foreground"
            >
                <li v-for="o in outcomes.earlier" :key="o.id">
                    <Link :href="o.url" class="underline">{{
                        o.evaluatedAtLabel
                    }}</Link>
                    · {{ o.resultLabel }} · {{ o.phaseLabel }}
                </li>
            </ul>
        </Card>
    </section>
</template>
