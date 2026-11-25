<script setup lang="ts">
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
        <h2 id="outcome-heading" class="text-lg/7 font-semibold">
            {{ outcomes?.heading ?? 'Observed change after actions' }}
        </h2>
        <p class="text-sm text-muted-foreground">
            {{
                outcomes?.standingSentence ??
                'This shows what changed after actions were taken. It does not show that the actions caused the change.'
            }}
        </p>
        <Card v-if="outcomes === null" class="p-4 text-sm text-muted-foreground"
            >No outcome has been recorded for this issue yet.</Card
        >
        <Card v-else class="gap-2 p-4 text-sm">
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
                class="text-primary-text underline"
                >Open the comparison</Link
            >
            <ul
                v-if="outcomes.earlier.length"
                class="mt-1 space-y-1 text-xs text-muted-foreground"
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
