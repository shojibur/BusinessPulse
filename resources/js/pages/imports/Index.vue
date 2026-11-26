<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, FileSpreadsheet, Upload } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import FreshnessBanner from '@/components/FreshnessBanner.vue';
import ImportStatusBadge from '@/components/ImportStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { Skeleton } from '@/components/ui/skeleton';
import { usePolling } from '@/composables/usePolling';
import { formatDate, formatDateTime, formatNumber } from '@/lib/format';
import { create, index, show } from '@/routes/imports';
import type { Freshness, ImportSummary, Pagination } from '@/types';

const props = defineProps<{
    imports: ImportSummary[];
    pagination: Pagination;
    filter: { status: string | null };
    statuses: { value: string; label: string }[];
    hasProcessing: boolean;
    freshness: Freshness | null;
}>();

const status = ref(props.filter.status ?? '');
const loading = ref(false);

// Refresh the list every 3 seconds while any listed import is processing.
const { failed, retry } = usePolling(
    () => props.hasProcessing,
    ['imports', 'hasProcessing', 'freshness', 'pagination'],
);

// Announce status changes to assistive technology.
const announcement = ref('');
const previous = new Map<number, string>();
watch(
    () => props.imports,
    (imports) => {
        for (const item of imports) {
            const before = previous.get(item.id);
            if (before !== undefined && before !== item.status) {
                announcement.value = `Import ${item.original_filename} is now ${item.status_label}.`;
            }
            previous.set(item.id, item.status);
        }
    },
    { immediate: true },
);

const stopStart = router.on('start', () => (loading.value = true));
const stopFinish = router.on('finish', () => (loading.value = false));
onBeforeUnmount(() => {
    stopStart();
    stopFinish();
});

function applyFilter(): void {
    router.get(index().url, status.value ? { status: status.value } : {}, {
        preserveState: true,
        replace: true,
    });
}

function clearFilter(): void {
    status.value = '';
    applyFilter();
}

const filtered = computed(() => props.filter.status !== null);
const range = (item: ImportSummary): string =>
    item.min_date
        ? `${formatDate(item.min_date)} to ${formatDate(item.max_date)}`
        : '—';
</script>

<template>
    <Head title="Data Imports" />

    <PageHeader
        title="Data Imports"
        description="Upload daily branch sales and see what each import changed."
    >
        <Button as-child>
            <Link :href="create().url">
                <Upload aria-hidden="true" />
                Upload sales data
            </Link>
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <FreshnessBanner v-if="freshness" :freshness="freshness" />

        <div class="sr-only" role="status" aria-live="polite">
            {{ announcement }}
        </div>

        <ErrorState
            v-if="failed"
            message="The list could not be refreshed."
            @retry="retry"
        />

        <Card class="gap-0 p-0">
            <div
                class="flex flex-wrap items-end justify-between gap-3 border-b px-5 py-4"
            >
                <SectionHeader
                    title="All imports"
                    :description="`${pagination.total} in total`"
                    :icon="FileSpreadsheet"
                />
                <div class="grid gap-1">
                    <label
                        for="status-filter"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Status
                    </label>
                    <NativeSelect
                        id="status-filter"
                        v-model="status"
                        class="min-w-48"
                        @update:model-value="applyFilter"
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </div>
            </div>

            <div v-if="loading" class="space-y-3 p-5" aria-busy="true">
                <Skeleton v-for="n in 4" :key="n" class="h-14 w-full" />
                <span class="sr-only">Loading imports</span>
            </div>

            <template v-else-if="imports.length === 0">
                <EmptyState
                    v-if="filtered"
                    :icon="FileSpreadsheet"
                    message="No imports have this status."
                >
                    <Button variant="outline" @click="clearFilter">
                        Clear filter
                    </Button>
                </EmptyState>
                <EmptyState
                    v-else
                    :icon="FileSpreadsheet"
                    message="No sales data has been imported yet."
                >
                    <Button as-child>
                        <Link :href="create().url">Upload sales data</Link>
                    </Button>
                </EmptyState>
            </template>

            <ul v-else class="divide-y">
                <li v-for="item in imports" :key="item.id">
                    <Link
                        :href="show(item.id).url"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 transition-colors outline-none hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary-text"
                        >
                            <FileSpreadsheet
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0 flex-1 basis-60">
                            <span
                                class="block text-sm font-medium break-all text-foreground"
                            >
                                {{ item.original_filename }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{ range(item) }} · {{ item.uploader ?? '—' }} ·
                                {{ formatDateTime(item.uploaded_at) }}
                            </span>
                        </span>
                        <span
                            class="flex items-center gap-5 text-sm tabular-nums"
                        >
                            <span class="text-right">
                                <span class="block font-semibold">{{
                                    formatNumber(item.total_rows)
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >rows</span
                                >
                            </span>
                            <span class="text-right">
                                <span
                                    class="block font-semibold"
                                    :class="
                                        item.invalid_rows > 0
                                            ? 'text-severity-medium'
                                            : ''
                                    "
                                    >{{ formatNumber(item.invalid_rows) }}</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >invalid</span
                                >
                            </span>
                        </span>
                        <ImportStatusBadge
                            :status="item.status"
                            :label="item.status_label"
                        />
                        <ChevronRight
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </Card>

        <nav
            v-if="pagination.last_page > 1"
            aria-label="Import pages"
            class="flex items-center justify-between text-sm"
        >
            <span class="text-muted-foreground tabular-nums">
                Page {{ pagination.current_page }} of
                {{ pagination.last_page }}
            </span>
            <span class="flex gap-4">
                <Link
                    v-if="pagination.prev_url"
                    :href="pagination.prev_url"
                    class="touch-target font-medium text-primary-text hover:underline"
                >
                    Previous
                </Link>
                <Link
                    v-if="pagination.next_url"
                    :href="pagination.next_url"
                    class="touch-target font-medium text-primary-text hover:underline"
                >
                    Next
                </Link>
            </span>
        </nav>
    </div>
</template>
