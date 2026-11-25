<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { TrendingUp } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ChangeFigures from '@/components/outcomes/ChangeFigures.vue';
import OutcomeResultBadge from '@/components/outcomes/OutcomeResultBadge.vue';
import PhaseBadge from '@/components/outcomes/PhaseBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { Skeleton } from '@/components/ui/skeleton';
import { index } from '@/routes/outcomes';
import type { OutcomeRow, Pagination } from '@/types';

const props = defineProps<{
    outcomes?: OutcomeRow[];
    pagination: Pagination;
    filters: { result: string | null; status: string | null };
    options: {
        results: { value: string; label: string }[];
        statuses: { value: string; label: string }[];
    };
}>();

const result = ref(props.filters.result ?? '');
const status = ref(props.filters.status ?? '');

function apply(): void {
    router.get(
        index().url,
        {
            ...(result.value ? { result: result.value } : {}),
            ...(status.value ? { status: status.value } : {}),
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Outcomes" />

    <PageHeader
        title="Outcomes"
        description="Observed change after actions. This shows what changed, not that the actions caused it."
    />

    <div class="space-y-6">
        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1">
                <label for="outcome-result" class="text-sm font-medium"
                    >Result</label
                >
                <NativeSelect
                    id="outcome-result"
                    v-model="result"
                    class="min-w-44"
                    @update:model-value="apply"
                >
                    <option value="">All results</option>
                    <option
                        v-for="o in options.results"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </NativeSelect>
            </div>
            <div class="grid gap-1">
                <label for="outcome-status" class="text-sm font-medium"
                    >Issue status</label
                >
                <NativeSelect
                    id="outcome-status"
                    v-model="status"
                    class="min-w-44"
                    @update:model-value="apply"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="o in options.statuses"
                        :key="o.value"
                        :value="o.value"
                    >
                        {{ o.label }}
                    </option>
                </NativeSelect>
            </div>
        </div>

        <Deferred data="outcomes">
            <template #fallback>
                <div class="space-y-3" aria-busy="true">
                    <Skeleton class="h-20 w-full" /><Skeleton
                        class="h-20 w-full"
                    />
                </div>
            </template>
            <Card v-if="!outcomes || outcomes.length === 0">
                <EmptyState
                    :icon="TrendingUp"
                    message="No outcomes yet. They appear after an issue has actions in progress and a later week has been evaluated."
                />
            </Card>
            <ul v-else class="space-y-3">
                <li v-for="o in outcomes" :key="o.id">
                    <Card class="gap-2 p-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <Link
                                :href="o.url"
                                class="touch-target font-medium text-primary-text hover:underline"
                                >{{ o.issueTitle }}</Link
                            >
                            <div class="flex flex-wrap items-center gap-2">
                                <OutcomeResultBadge
                                    :result="o.result"
                                    :label="o.resultLabel"
                                />
                                <PhaseBadge
                                    :label="o.phaseLabel"
                                    :used-for-verification="
                                        o.usedForVerification
                                    "
                                />
                            </div>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ o.departmentName }} · {{ o.ruleName }} · issue
                            {{ o.issueStatusLabel }} · {{ o.evaluatedAtLabel }}
                        </p>
                        <p v-if="o.notEvaluableText" class="text-sm">
                            {{ o.notEvaluableText }}
                        </p>
                        <p v-else class="text-sm">
                            {{ o.outcomeMetricLabel }}: {{ o.baselineLabel }} →
                            {{ o.followupLabel }} ·
                            <ChangeFigures :labels="o.changeLabels" />
                        </p>
                    </Card>
                </li>
            </ul>
            <nav
                v-if="pagination.last_page > 1"
                class="mt-4 flex items-center justify-between"
                aria-label="Outcome pages"
            >
                <Button
                    variant="outline"
                    :disabled="!pagination.prev_url"
                    as-child
                >
                    <Link v-if="pagination.prev_url" :href="pagination.prev_url"
                        >Previous</Link
                    >
                    <span v-else>Previous</span>
                </Button>
                <span class="text-sm text-muted-foreground"
                    >Page {{ pagination.current_page }} of
                    {{ pagination.last_page }}</span
                >
                <Button
                    variant="outline"
                    :disabled="!pagination.next_url"
                    as-child
                >
                    <Link v-if="pagination.next_url" :href="pagination.next_url"
                        >Next</Link
                    >
                    <span v-else>Next</span>
                </Button>
            </nav>
        </Deferred>
    </div>
</template>
