<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ErrorState from '@/components/ErrorState.vue';
import ImportErrorsTable from '@/components/ImportErrorsTable.vue';
import ImportPreviewPanel from '@/components/ImportPreviewPanel.vue';
import ImportStatusBadge from '@/components/ImportStatusBadge.vue';
import KpiCard from '@/components/KpiCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import WeekStateList from '@/components/WeekStateList.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { usePolling } from '@/composables/usePolling';
import {
    formatBytes,
    formatDate,
    formatDateTime,
    formatNumber,
} from '@/lib/format';
import { cancel, confirm, index, retry } from '@/routes/imports';
import type {
    ImportDetail,
    ImportErrorRow,
    ImportPreview,
    Pagination,
    WeekStateView,
} from '@/types';

const props = defineProps<{
    record: ImportDetail;
    preview: ImportPreview | null;
    previewError: string | null;
    weeks: WeekStateView[];
    rowErrors: { data: ImportErrorRow[]; pagination: Pagination };
    actions: { confirm: boolean; cancel: boolean; retry: boolean };
}>();

const busy = ref(false);
const announcement = ref('');

// Poll the status every 3 seconds while the job runs.
const { failed, retry: refresh } = usePolling(
    () => props.record.status === 'processing',
    ['record', 'weeks', 'rowErrors', 'actions'],
);

watch(
    () => props.record.status_label,
    (label) => {
        announcement.value = `Import status: ${label}.`;
        busy.value = false;
    },
);

function post(url: string): void {
    // Disabled while the request runs, so a double click cannot submit twice.
    busy.value = true;
    router.post(url, {}, { onFinish: () => (busy.value = false) });
}

const isProcessing = computed(() => props.record.status === 'processing');
const isFinished = computed(
    () =>
        props.record.status === 'completed' ||
        props.record.status === 'completed_with_errors',
);
</script>

<template>
    <Head :title="`Import ${record.original_filename}`" />

    <PageHeader :title="record.original_filename">
        <ImportStatusBadge
            :status="record.status"
            :label="record.status_label"
        />
        <Button variant="outline" as-child>
            <Link :href="index().url">Back to imports</Link>
        </Button>
    </PageHeader>

    <div class="sr-only" role="status" aria-live="polite">
        {{ announcement }}
    </div>

    <div class="space-y-6">
        <ErrorState
            v-if="failed"
            message="The status could not be refreshed."
            @retry="refresh"
        />

        <Alert v-if="isProcessing" role="status">
            <Spinner aria-hidden="true" />
            <AlertTitle>Processing</AlertTitle>
            <AlertDescription>
                The file is being imported. This page updates by itself.
            </AlertDescription>
        </Alert>

        <Alert v-if="record.status === 'failed'" variant="destructive">
            <TriangleAlert aria-hidden="true" />
            <AlertTitle>The import failed</AlertTitle>
            <AlertDescription>
                {{
                    record.error_message ?? 'The import could not be processed.'
                }}
                No sales data was changed.
            </AlertDescription>
        </Alert>

        <Alert v-if="record.status === 'cancelled'" role="status">
            <AlertTitle>Cancelled</AlertTitle>
            <AlertDescription>
                This import was cancelled. No sales data was changed.
            </AlertDescription>
        </Alert>

        <Alert v-if="isFinished && record.invalid_rows > 0" role="status">
            <TriangleAlert aria-hidden="true" />
            <AlertTitle>Some rows were not imported</AlertTitle>
            <AlertDescription>
                {{ record.invalid_rows }} invalid row(s) were not imported. The
                valid rows were saved. See the errors below.
            </AlertDescription>
        </Alert>

        <div
            v-if="actions.confirm || actions.cancel || actions.retry"
            class="flex flex-wrap gap-3"
        >
            <Button
                v-if="actions.confirm"
                :disabled="busy"
                @click="post(confirm(record.id).url)"
            >
                Confirm import
            </Button>
            <Button
                v-if="actions.retry"
                :disabled="busy"
                @click="post(retry(record.id).url)"
            >
                Retry import
            </Button>
            <Button
                v-if="actions.cancel"
                variant="outline"
                :disabled="busy"
                @click="post(cancel(record.id).url)"
            >
                Cancel import
            </Button>
        </div>

        <section v-if="record.status === 'validated'" class="space-y-3">
            <h2 class="text-lg/7 font-semibold">Preview</h2>
            <p class="text-sm text-muted-foreground">
                Nothing has been saved yet. Review the result, then confirm or
                cancel.
            </p>
            <Alert v-if="previewError" variant="destructive">
                <TriangleAlert aria-hidden="true" />
                <AlertDescription>{{ previewError }}</AlertDescription>
            </Alert>
            <ImportPreviewPanel v-else-if="preview" :preview="preview" />
            <Card v-else class="gap-3 p-4" aria-busy="true">
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-24 w-full" />
            </Card>
        </section>

        <section
            v-if="!isProcessing && record.status !== 'validated'"
            class="space-y-3"
        >
            <h2 class="text-lg/7 font-semibold">
                {{ isFinished ? 'Result' : 'Counts at validation' }}
            </h2>
            <p v-if="!isFinished" class="text-sm text-muted-foreground">
                These are the counts from when the file was checked. Nothing was
                imported.
            </p>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                <KpiCard
                    label="Total rows"
                    :value="formatNumber(record.total_rows)"
                />
                <KpiCard
                    label="Valid"
                    :value="formatNumber(record.valid_rows)"
                />
                <KpiCard
                    label="Invalid"
                    :value="formatNumber(record.invalid_rows)"
                />
                <KpiCard
                    label="Inserted"
                    :value="formatNumber(record.inserted_rows)"
                />
                <KpiCard
                    label="Corrected"
                    :value="formatNumber(record.corrected_rows)"
                />
                <KpiCard
                    label="Unchanged"
                    :value="formatNumber(record.unchanged_rows)"
                />
            </div>
        </section>

        <section v-if="isFinished && weeks.length > 0" class="space-y-3">
            <h2 class="text-lg/7 font-semibold">Reporting weeks covered</h2>
            <p class="text-sm text-muted-foreground">
                The current state of each week this file touches. Missing data
                is never treated as zero.
            </p>
            <WeekStateList :weeks="weeks" />
        </section>

        <section class="space-y-3">
            <h2 class="text-lg/7 font-semibold">Details</h2>
            <Card class="gap-0 p-0">
                <dl class="divide-y text-sm">
                    <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-muted-foreground">File</dt>
                        <dd class="font-medium break-all sm:col-span-2">
                            {{ record.original_filename }}
                            ({{ formatBytes(record.file_size) }})
                        </dd>
                    </div>
                    <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-muted-foreground">Date range</dt>
                        <dd class="font-medium sm:col-span-2">
                            <template v-if="record.min_date">
                                {{ formatDate(record.min_date) }} to
                                {{ formatDate(record.max_date) }}
                            </template>
                            <template v-else>—</template>
                        </dd>
                    </div>
                    <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-muted-foreground">Uploaded</dt>
                        <dd class="font-medium sm:col-span-2">
                            {{ formatDateTime(record.uploaded_at) }} by
                            {{ record.uploader ?? '—' }}
                        </dd>
                    </div>
                    <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-muted-foreground">Confirmed</dt>
                        <dd class="font-medium sm:col-span-2">
                            {{ formatDateTime(record.confirmed_at) }}
                        </dd>
                    </div>
                    <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-muted-foreground">Completed</dt>
                        <dd class="font-medium sm:col-span-2">
                            {{ formatDateTime(record.completed_at) }}
                        </dd>
                    </div>
                    <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-muted-foreground">File fingerprint</dt>
                        <dd class="font-mono text-xs break-all sm:col-span-2">
                            {{ record.file_hash ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </Card>
        </section>

        <section v-if="record.status !== 'processing'" class="space-y-3">
            <h2 class="text-lg/7 font-semibold">Row errors</h2>
            <ImportErrorsTable
                :errors="rowErrors.data"
                :pagination="rowErrors.pagination"
            />
        </section>
    </div>
</template>
