<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Info,
    ListChecks,
    RefreshCw,
    TriangleAlert,
    Volume2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AiUnavailableNotice from '@/components/AiUnavailableNotice.vue';
import AudioPlayer from '@/components/AudioPlayer.vue';
import BriefSourceBadge from '@/components/BriefSourceBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import SeverityBadge from '@/components/SeverityBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateRange, formatDateTime } from '@/lib/format';
import { index, retry, store } from '@/routes/briefs';
import { store as audioStore } from '@/routes/briefs/audio';
import type {
    AiAvailability,
    AudioView,
    BriefAdjacent,
    BriefView,
} from '@/types';

const props = defineProps<{
    brief: BriefView;
    audio: AudioView;
    ai: AiAvailability;
    adjacent: BriefAdjacent;
}>();

const inProgress = computed(
    () =>
        props.brief.status === 'queued' || props.brief.status === 'generating',
);
const retrying = ref(false);
const writingNew = ref(false);

// The same data gives the same brief, so a new one is only written on request.
function writeNewBrief(): void {
    writingNew.value = true;
    router.post(
        store().url,
        { force: true },
        { onFinish: () => (writingNew.value = false) },
    );
}

// A coloured left edge shows how serious each finding is.
const severityEdge: Record<string, string> = {
    high: 'border-l-severity-high',
    medium: 'border-l-severity-medium',
    low: 'border-l-slate-300',
};

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
                () => router.reload({ only: ['brief', 'audio', 'ai'] }),
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
        <Button v-if="adjacent.previous" variant="outline" size="sm" as-child>
            <Link
                :href="adjacent.previous.url"
                :title="adjacent.previous.label"
            >
                <ChevronLeft aria-hidden="true" />
                Earlier brief
            </Link>
        </Button>
        <Button v-if="adjacent.next" variant="outline" size="sm" as-child>
            <Link :href="adjacent.next.url" :title="adjacent.next.label">
                Later brief
                <ChevronRight aria-hidden="true" />
            </Link>
        </Button>
        <Button
            v-if="brief.status === 'completed'"
            variant="outline"
            size="sm"
            :disabled="writingNew"
            @click="writeNewBrief"
        >
            <Spinner v-if="writingNew" />
            <RefreshCw v-else aria-hidden="true" />
            Write a new brief
        </Button>
        <Button variant="outline" as-child>
            <Link :href="index().url">All briefs</Link>
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <AiUnavailableNotice v-if="!ai.text" />

        <Card v-if="inProgress" class="space-y-4 p-6" aria-busy="true">
            <p class="flex items-center gap-2 text-sm" role="status">
                <Spinner />
                {{
                    brief.status === 'queued'
                        ? 'Your brief is queued…'
                        : 'Writing your brief…'
                }}
            </p>
            <Alert v-if="brief.stalled" variant="destructive" role="alert">
                <TriangleAlert aria-hidden="true" />
                <AlertTitle>This is taking longer than expected</AlertTitle>
                <AlertDescription>
                    Briefs are written by a background worker, and it does not
                    seem to be running. Ask your administrator to start it (the
                    queue worker), then reload this page.
                </AlertDescription>
            </Alert>
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
            <!-- The headline: the one sentence an owner needs. -->
            <Card class="gap-3 border-l-4 border-l-primary p-6">
                <h2 class="text-xl/7 font-semibold tracking-tight">
                    {{ brief.headline }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ coverageLabel }} · Generated
                    <span :title="formatDateTime(brief.generated_at)">{{
                        brief.ago ?? formatDateTime(brief.generated_at)
                    }}</span>
                    ·
                    {{ brief.notice }}
                </p>
            </Card>

            <Alert v-if="brief.limitation" role="status">
                <Info aria-hidden="true" />
                <AlertTitle>Limitations</AlertTitle>
                <AlertDescription>{{ brief.limitation }}</AlertDescription>
            </Alert>

            <div
                class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            >
                <section
                    v-if="brief.items.length"
                    aria-labelledby="findings-heading"
                    class="min-w-0 space-y-3"
                >
                    <SectionHeader
                        id="findings-heading"
                        title="Findings"
                        description="The issues that need attention, most serious first."
                        :icon="ListChecks"
                    />
                    <Card
                        v-for="item in brief.items"
                        :key="item.position"
                        class="gap-2 border-l-4 p-5"
                        :class="severityEdge[item.severity]"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="flex size-6 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                                >{{ item.position }}</span
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
                            class="w-fit rounded-md bg-muted/60 px-3 py-1.5 text-sm text-muted-foreground tabular-nums"
                        >
                            {{ item.figures }}
                        </p>
                        <p
                            v-if="item.note"
                            class="text-sm text-muted-foreground"
                        >
                            {{ item.note }}
                        </p>
                    </Card>
                </section>

                <aside class="min-w-0 space-y-6">
                    <Card class="gap-4 p-5">
                        <SectionHeader title="Listen" :icon="Volume2" />
                        <AudioPlayer
                            :audio="audio"
                            :request-url="audioStore(brief.id).url"
                        />
                    </Card>
                </aside>
            </div>
        </template>
    </div>
</template>
