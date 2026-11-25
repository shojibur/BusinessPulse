<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileSpreadsheet, Upload } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import FreshnessBanner from '@/components/FreshnessBanner.vue';
import ImportStatusBadge from '@/components/ImportStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1">
                <label for="status-filter" class="text-sm font-medium">
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

        <ErrorState
            v-if="failed"
            message="The list could not be refreshed."
            @retry="retry"
        />

        <Card v-if="loading" class="gap-3 p-4" aria-busy="true">
            <Skeleton v-for="n in 4" :key="n" class="h-10 w-full" />
            <span class="sr-only">Loading imports</span>
        </Card>

        <Card v-else-if="imports.length === 0">
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
        </Card>

        <template v-else>
            <!-- Table from 768px, stacked cards below. -->
            <Card class="hidden gap-0 p-0 md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Status</TableHead>
                            <TableHead>File</TableHead>
                            <TableHead>Date range</TableHead>
                            <TableHead class="text-right">Rows</TableHead>
                            <TableHead class="text-right">Invalid</TableHead>
                            <TableHead>Uploaded by</TableHead>
                            <TableHead>Uploaded</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in imports" :key="item.id">
                            <TableCell>
                                <ImportStatusBadge
                                    :status="item.status"
                                    :label="item.status_label"
                                />
                            </TableCell>
                            <TableCell class="max-w-xs break-all">
                                <Link
                                    :href="show(item.id).url"
                                    class="touch-target font-medium text-primary-text hover:underline"
                                >
                                    {{ item.original_filename }}
                                </Link>
                            </TableCell>
                            <TableCell>{{ range(item) }}</TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatNumber(item.total_rows) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatNumber(item.invalid_rows) }}
                            </TableCell>
                            <TableCell>{{ item.uploader ?? '—' }}</TableCell>
                            <TableCell>
                                {{ formatDateTime(item.uploaded_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <ul class="space-y-3 md:hidden">
                <li v-for="item in imports" :key="item.id">
                    <Card class="gap-2 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <Link
                                :href="show(item.id).url"
                                class="touch-target font-medium break-all text-primary-text hover:underline"
                            >
                                {{ item.original_filename }}
                            </Link>
                            <ImportStatusBadge
                                :status="item.status"
                                :label="item.status_label"
                            />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ range(item) }}
                        </p>
                        <p class="text-sm tabular-nums">
                            {{ formatNumber(item.total_rows) }} rows,
                            {{ formatNumber(item.invalid_rows) }} invalid
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ item.uploader ?? '—' }} ·
                            {{ formatDateTime(item.uploaded_at) }}
                        </p>
                    </Card>
                </li>
            </ul>

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
        </template>
    </div>
</template>
