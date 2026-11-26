<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    CircleCheck,
    CirclePlus,
    ClipboardCheck,
    FileSpreadsheet,
    History,
    Minus,
    Pencil,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ErrorState from '@/components/ErrorState.vue';
import ImportErrorsTable from '@/components/ImportErrorsTable.vue';
import ImportPreviewPanel from '@/components/ImportPreviewPanel.vue';
import ImportStatusBadge from '@/components/ImportStatusBadge.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import TimelineList from '@/components/TimelineList.vue';
import type { TimelineItem } from '@/components/TimelineList.vue';
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

// A short story of the import, from the timestamps that exist.
const timeline = computed<TimelineItem[]>(() => {
    const r = props.record;
    const items: TimelineItem[] = [
        {
            key: 'uploaded',
            title: 'File uploaded',
            meta: `${formatDateTime(r.uploaded_at)} · ${r.uploader ?? '—'}`,
            tone: 'info',
        },
    ];

    if (r.confirmed_at) {
        items.push({
            key: 'confirmed',
            title: 'Import confirmed',
            meta: formatDateTime(r.confirmed_at),
            tone: 'info',
        });
    }

    if (r.status === 'cancelled') {
        items.push({ key: 'cancelled', title: 'Cancelled', tone: 'neutral' });
    } else if (r.status === 'failed') {
        items.push({
            key: 'failed',
            title: 'Import failed',
            meta: r.completed_at ? formatDateTime(r.completed_at) : undefined,
            tone: 'danger',
        });
    } else if (r.completed_at) {
        items.push({
            key: 'completed',
            title:
                r.status === 'completed_with_errors'
                    ? 'Completed with errors'
                    : 'Import completed',
            meta: formatDateTime(r.completed_at),
            tone: r.status === 'completed_with_errors' ? 'warning' : 'success',
        });
    } else if (r.status === 'validated') {
        items.push({
            key: 'waiting',
            title: 'Waiting for your confirmation',
            tone: 'warning',
        });
    }

    return items;
});

const isProcessing = computed(() => props.record.status === 'processing');
const isFinished = computed(
    () =>
        props.record.status === 'completed' ||
        props.record.status === 'completed_with_errors',
);
</script>

<template>
    <Head :title="`Import ${record.original_filename}`" />

    <PageHeader
        :title="record.original_filename"
        :description="`Uploaded ${formatDateTime(record.uploaded_at)} by ${record.uploader ?? '—'}`"
    >
        <ImportStatusBadge
            :status="record.status"
            :label="record.status_label"
        />
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
        <Button variant="outline" as-child>
            <Link :href="index().url">
                <ChevronLeft aria-hidden="true" />
                All imports
            </Link>
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

        <!-- The headline counts first. -->
        <section
            v-if="!isProcessing && record.status !== 'validated'"
            class="space-y-3"
        >
            <SectionHeader
                :title="isFinished ? 'Result' : 'Counts at validation'"
                :description="
                    isFinished
                        ? 'What this import did to your sales data.'
                        : 'From when the file was checked. Nothing was imported.'
                "
                :icon="FileSpreadsheet"
            />
            <div
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6"
            >
                <MetricTile
                    label="Total rows"
                    :value="formatNumber(record.total_rows)"
                    :icon="FileSpreadsheet"
                />
                <MetricTile
                    label="Valid"
                    :value="formatNumber(record.valid_rows)"
                    :icon="CircleCheck"
                    tone="success"
                />
                <MetricTile
                    label="Invalid"
                    :value="formatNumber(record.invalid_rows)"
                    :icon="TriangleAlert"
                    :tone="record.invalid_rows > 0 ? 'warning' : 'neutral'"
                />
                <MetricTile
                    label="Inserted"
                    :value="formatNumber(record.inserted_rows)"
                    :icon="CirclePlus"
                    tone="info"
                />
                <MetricTile
                    label="Corrected"
                    :value="formatNumber(record.corrected_rows)"
                    :icon="Pencil"
                />
                <MetricTile
                    label="Unchanged"
                    :value="formatNumber(record.unchanged_rows)"
                    :icon="Minus"
                />
            </div>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0 space-y-6">
                <section v-if="record.status === 'validated'" class="space-y-3">
                    <SectionHeader
                        title="Preview"
                        description="Nothing has been saved yet. Review the result, then confirm or cancel."
                        :icon="ClipboardCheck"
                    />
                    <Alert v-if="previewError" variant="destructive">
                        <TriangleAlert aria-hidden="true" />
                        <AlertDescription>{{ previewError }}</AlertDescription>
                    </Alert>
                    <ImportPreviewPanel
                        v-else-if="preview"
                        :preview="preview"
                    />
                    <Card v-else class="gap-3 p-5" aria-busy="true">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-24 w-full" />
                    </Card>
                </section>

                <section
                    v-if="isFinished && weeks.length > 0"
                    class="space-y-3"
                >
                    <SectionHeader
                        title="Reporting weeks covered"
                        description="The current state of each week this file touches. Missing data is never treated as zero."
                        :icon="CalendarDays"
                    />
                    <WeekStateList :weeks="weeks" />
                </section>

                <section
                    v-if="record.status !== 'processing'"
                    class="space-y-3"
                >
                    <SectionHeader
                        title="Row errors"
                        description="Rows that were not imported, and why."
                        :icon="TriangleAlert"
                    />
                    <ImportErrorsTable
                        :errors="rowErrors.data"
                        :pagination="rowErrors.pagination"
                    />
                </section>
            </div>

            <aside class="min-w-0 space-y-6">
                <Card class="gap-4 p-5">
                    <SectionHeader title="Details" />
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">File</dt>
                            <dd class="font-medium break-all">
                                {{ record.original_filename }}
                                <span class="font-normal text-muted-foreground">
                                    ({{ formatBytes(record.file_size) }})
                                </span>
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Date range
                            </dt>
                            <dd class="font-medium">
                                <template v-if="record.min_date">
                                    {{ formatDate(record.min_date) }} to
                                    {{ formatDate(record.max_date) }}
                                </template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                File fingerprint
                            </dt>
                            <dd class="font-mono text-xs break-all">
                                {{ record.file_hash ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </Card>

                <Card class="gap-4 p-5">
                    <SectionHeader title="What happened" :icon="History" />
                    <TimelineList :items="timeline" />
                </Card>
            </aside>
        </div>
    </div>
</template>
