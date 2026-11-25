<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Info, RefreshCw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AudioPlayer from '@/components/AudioPlayer.vue';
import BriefSourceBadge from '@/components/BriefSourceBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SeverityBadge from '@/components/SeverityBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateRange, formatDateTime } from '@/lib/format';
import { index, retry } from '@/routes/briefs';
import { store as audioStore } from '@/routes/briefs/audio';
import type { AudioView, BriefView } from '@/types';

const props = defineProps<{ brief: BriefView; audio: AudioView }>();

const inProgress = computed(
    () =>
        props.brief.status === 'queued' || props.brief.status === 'generating',
);
const retrying = ref(false);

// Poll every 3 seconds while the brief is being generated.
let timer: ReturnType<typeof setInterval> | null = null;

watch(
    inProgress,
    (active) => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }

        if (active) {
            timer = setInterval(
                () => router.reload({ only: ['brief', 'audio'] }),
                3000,
            );
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => timer !== null && clearInterval(timer));

function retryBrief(): void {
    retrying.value = true;
    router.post(
        retry(props.brief.id).url,
        {},
        { preserveScroll: true, onFinish: () => (retrying.value = false) },
    );
}

const coverageLabel = computed(
    () =>
        ({
            sufficient: 'Sufficient coverage',
            partial: 'Partial coverage',
            stale: 'Data not up to date',
        })[props.brief.coverage_status ?? 'sufficient'],
);
</script>

<template>
    <Head title="Brief" />

    <PageHeader
        title="Executive brief"
        :description="
            brief.period_start && brief.period_end
                ? `Latest closed week: ${formatDateRange(brief.period_start, brief.period_end)}`
                : undefined
        "
    >
        <BriefSourceBadge :status="brief.status" :source="brief.source" />
        <Button variant="outline" as-child>
            <Link :href="index().url">All briefs</Link>
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <Card v-if="inProgress" class="p-6" aria-busy="true">
            <p class="flex items-center gap-2 text-sm" role="status">
                <Spinner />
                {{
                    brief.status === 'queued'
                        ? 'Your brief is queued…'
                        : 'Writing your brief…'
                }}
            </p>
        </Card>

        <Alert
            v-else-if="brief.status === 'failed'"
            variant="destructive"
            role="alert"
        >
            <Info aria-hidden="true" />
            <AlertTitle>The brief could not be generated</AlertTitle>
            <AlertDescription class="space-y-2">
                <p>{{ brief.notice }}</p>
                <Button size="sm" :disabled="retrying" @click="retryBrief">
                    <Spinner v-if="retrying" />
                    <RefreshCw v-else aria-hidden="true" />
                    Try again
                </Button>
            </AlertDescription>
        </Alert>

        <template v-else>
            <Card class="space-y-3 p-6">
                <h2 class="text-lg font-semibold">{{ brief.headline }}</h2>
                <p class="text-xs text-muted-foreground">
                    {{ coverageLabel }} · Generated
                    {{ formatDateTime(brief.generated_at) }} ·
                    {{ brief.notice }}
                </p>
            </Card>

            <Alert v-if="brief.limitation" role="status">
                <Info aria-hidden="true" />
                <AlertTitle>Limitations</AlertTitle>
                <AlertDescription>{{ brief.limitation }}</AlertDescription>
            </Alert>

            <section
                v-if="brief.items.length"
                aria-labelledby="findings-heading"
                class="space-y-3"
            >
                <h2 id="findings-heading" class="text-base font-semibold">
                    Findings
                </h2>
                <Card
                    v-for="item in brief.items"
                    :key="item.position"
                    class="space-y-2 p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-muted-foreground"
                            >{{ item.position }}.</span
                        >
                        <SeverityBadge :severity="item.severity" />
                        <span class="text-xs text-muted-foreground">{{
                            item.period_label
                        }}</span>
                    </div>
                    <h3 class="font-medium">
                        <Link
                            v-if="item.issue_url"
                            :href="item.issue_url"
                            class="text-primary-text hover:underline"
                            >{{ item.title }}</Link
                        >
                        <template v-else>{{ item.title }}</template>
                    </h3>
                    <p class="text-sm">{{ item.explanation }}</p>
                    <p
                        v-if="item.figures"
                        class="text-sm text-muted-foreground tabular-nums"
                    >
                        {{ item.figures }}
                    </p>
                    <p v-if="item.note" class="text-sm text-muted-foreground">
                        {{ item.note }}
                    </p>
                </Card>
            </section>

            <Card class="p-4">
                <AudioPlayer
                    :audio="audio"
                    :request-url="audioStore(brief.id).url"
                />
            </Card>
        </template>
    </div>
</template>
