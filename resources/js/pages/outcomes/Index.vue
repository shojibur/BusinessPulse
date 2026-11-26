<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronRight,
    CircleCheck,
    Minus,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
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

const resultIcon = (r: string) =>
    r === 'improved' ? CircleCheck : r === 'worsened' ? TrendingDown : Minus;

const resultTile = (r: string): string =>
    r === 'improved'
        ? 'bg-success-bg text-success'
        : r === 'worsened'
          ? 'bg-severity-high-bg text-severity-high'
          : 'bg-muted text-muted-foreground';

const changeColor = (r: string): string =>
    r === 'improved'
        ? 'text-success'
        : r === 'worsened'
          ? 'text-severity-high'
          : 'text-foreground';
</script>

<template>
    <Head title="Outcomes" />

    <PageHeader
        title="Outcomes"
        description="What changed after management actions were taken."
    />

    <div class="space-y-6">
        <Card class="gap-0 overflow-hidden p-0">
            <div
                class="flex flex-wrap items-end gap-3 border-b bg-muted/30 px-5 py-4"
            >
                <div class="grid gap-1">
                    <label
                        for="outcome-result"
                        class="text-xs font-medium text-muted-foreground"
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
                    <label
                        for="outcome-status"
                        class="text-xs font-medium text-muted-foreground"
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
                <p class="ms-auto text-xs text-muted-foreground tabular-nums">
                    {{ pagination.total }} outcome{{
                        pagination.total === 1 ? '' : 's'
                    }}
                </p>
            </div>

            <Deferred data="outcomes">
                <template #fallback>
                    <div class="space-y-3 p-5" aria-busy="true">
                        <Skeleton class="h-16 w-full" /><Skeleton
                            class="h-16 w-full"
                        />
                    </div>
                </template>
                <EmptyState
                    v-if="!outcomes || outcomes.length === 0"
                    :icon="TrendingUp"
                    message="No outcomes yet. They appear after an issue has actions in progress and a later week has been evaluated."
                />
                <ul v-else class="divide-y">
                    <li v-for="o in outcomes" :key="o.id">
                        <Link
                            :href="o.url"
                            class="group flex flex-wrap items-center gap-4 px-5 py-4 outline-none transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:ring-inset"
                        >
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                                :class="resultTile(o.result)"
                            >
                                <component
                                    :is="resultIcon(o.result)"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm/5 font-semibold text-foreground"
                                    >{{ o.issueTitle }}</span
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                >
                                    {{ o.departmentName }} · {{ o.ruleName }} ·
                                    issue {{ o.issueStatusLabel }} · evaluated
                                    {{ o.evaluatedAtLabel }}
                                </span>
                                <span
                                    v-if="o.notEvaluableText"
                                    class="mt-1 block text-xs"
                                    >{{ o.notEvaluableText }}</span
                                >
                                <span
                                    v-else
                                    class="mt-1 block text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ o.outcomeMetricLabel }}:
                                    <span class="font-medium text-foreground">{{
                                        o.baselineLabel
                                    }}</span>
                                    →
                                    <span class="font-medium text-foreground">{{
                                        o.followupLabel
                                    }}</span>
                                </span>
                            </span>
                            <span
                                v-if="!o.notEvaluableText"
                                class="text-base/6 font-semibold"
                                :class="changeColor(o.result)"
                            >
                                <ChangeFigures :labels="o.changeLabels" />
                            </span>
                            <span class="flex flex-wrap items-center gap-2">
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
                                <ChevronRight
                                    class="hidden size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 sm:block"
                                    aria-hidden="true"
                                />
                            </span>
                        </Link>
                    </li>
                </ul>
            </Deferred>
        </Card>

        <Deferred data="outcomes">
            <template #fallback><span /></template>
            <nav
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between"
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

        <p class="px-1 text-xs text-muted-foreground">
            Observed change after actions, not proof that the actions caused it.
        </p>
    </div>
</template>
