<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import FileDropzone from '@/components/FileDropzone.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index, store, template } from '@/routes/imports';

const props = defineProps<{
    header: string;
    limits: { max_megabytes: number; max_rows: number };
    expectedBranches: string[];
}>();

const file = ref<File | null>(null);
const form = useForm<{ file: File | null }>({ file: null });

function select(selected: File | null): void {
    file.value = selected;
    form.file = selected;
    form.clearErrors();
}

function submit(): void {
    form.post(store().url, { forceFormData: true });
}
</script>

<template>
    <Head title="Upload sales data" />

    <PageHeader
        title="Upload sales data"
        description="Check a CSV file before anything is saved."
    >
        <Button variant="outline" as-child>
            <Link :href="index().url">Back to imports</Link>
        </Button>
    </PageHeader>

    <div class="mx-auto max-w-3xl space-y-6">
        <Alert v-if="props.expectedBranches.length === 0" variant="destructive">
            <TriangleAlert aria-hidden="true" />
            <AlertTitle>Expected branches are not configured</AlertTitle>
            <AlertDescription>
                Sales data cannot be imported until the expected branches of
                this department are set. Ask the person who administers
                BusinessPulse to configure them.
            </AlertDescription>
        </Alert>

        <Card class="gap-4 p-6">
            <h2 class="text-lg/7 font-semibold">File format</h2>
            <p class="text-sm text-muted-foreground">
                One row per branch per day, comma separated, UTF-8, with this
                header (column order is free):
            </p>
            <pre
                class="overflow-x-auto rounded-md bg-muted p-3 text-xs"
            ><code>{{ header }}</code></pre>
            <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                <li>
                    Up to {{ limits.max_megabytes }} MB and
                    {{ limits.max_rows.toLocaleString('en-MY') }} data rows.
                </li>
                <li>
                    Amounts are plain decimals such as 4520.50. Zero is allowed.
                    Leave order_count empty when unknown.
                </li>
                <li>Only the expected branches below are accepted.</li>
            </ul>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted-foreground">Expected branches:</span>
                <StatusBadge
                    v-for="branch in expectedBranches"
                    :key="branch"
                    :label="branch"
                    tone="info"
                />
                <span v-if="expectedBranches.length === 0">None</span>
            </div>
            <div>
                <Button variant="outline" as-child>
                    <a :href="template().url" download>
                        <Download aria-hidden="true" />
                        Download the CSV template
                    </a>
                </Button>
            </div>
        </Card>

        <form class="space-y-4" novalidate @submit.prevent="submit">
            <FileDropzone
                :file="file"
                :disabled="form.processing || expectedBranches.length === 0"
                :invalid="!!form.errors.file"
                @select="select"
            />

            <!-- Announced to assistive technology when a rejection appears. -->
            <div role="alert" aria-live="assertive">
                <p
                    v-if="form.errors.file"
                    class="text-sm font-medium text-severity-high"
                >
                    {{ form.errors.file }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    type="submit"
                    :disabled="
                        !file ||
                        form.processing ||
                        expectedBranches.length === 0
                    "
                >
                    <Spinner v-if="form.processing" />
                    {{ form.processing ? 'Checking the file…' : 'Check file' }}
                </Button>
                <p class="text-sm text-muted-foreground">
                    Nothing is saved until you confirm the preview.
                </p>
            </div>
        </form>
    </div>
</template>
