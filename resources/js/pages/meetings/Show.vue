<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleAlert,
    CircleCheck,
    ClipboardList,
    Mic,
    Pencil,
    Plus,
    Quote,
    RefreshCw,
    ShieldCheck,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import DeleteRecordingDialog from '@/components/meetings/DeleteRecordingDialog.vue';
import DraftsOverviewTable from '@/components/meetings/DraftsOverviewTable.vue';
import ManualTranscriptForm from '@/components/meetings/ManualTranscriptForm.vue';
import MeetingBriefStrip from '@/components/meetings/MeetingBriefStrip.vue';
import MeetingsListCard from '@/components/meetings/MeetingsListCard.vue';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import RecordingUploadCard from '@/components/meetings/RecordingUploadCard.vue';
import TaskDraftsTab from '@/components/meetings/TaskDraftsTab.vue';
import TranscriptPanel from '@/components/meetings/TranscriptPanel.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import StepTracker from '@/components/StepTracker.vue';
import type { Step } from '@/components/StepTracker.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatBytes, formatDate, formatDateTime } from '@/lib/format';
import { create, edit, retry } from '@/routes/meetings';
import type {
    DraftReview,
    LatestBriefSummary,
    MeetingDetail,
    MeetingLimits,
    MeetingSidebarItem,
    TaskDraftSummary,
} from '@/types';

const props = defineProps<{
    meeting: MeetingDetail;
    transcript: string | null;
    taskDrafts: TaskDraftSummary[];
    draftReview: DraftReview | null;
    limits: MeetingLimits;
    sidebar: MeetingSidebarItem[];
    latestBrief: LatestBriefSummary | null;
    businessToday: string;
}>();

const retrying = ref(false);
const retryError = ref<string | null>(null);

// Poll every 3 seconds while a background job owns the meeting; reload everything once when it ends.
let timer: ReturnType<typeof setInterval> | null = null;

watch(
    () => props.meeting.polling,
    (polling, wasPolling) => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }

        if (polling) {
            timer = setInterval(
                () => router.reload({ only: ['meeting'] }),
                3000,
            );
        } else if (wasPolling) {
            router.reload();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => timer !== null && clearInterval(timer));

function reExtract(): void {
    retrying.value = true;
    router.post(
        `/meetings/${props.meeting.id}/re-extract`,
        {},
        { preserveScroll: true, onFinish: () => (retrying.value = false) },
    );
}

function retryStage(): void {
    retrying.value = true;
    retryError.value = null;
    router.post(
        retry(props.meeting.id).url,
        {},
        {
            preserveScroll: true,
            onError: (bag) =>
                (retryError.value =
                    bag.retry ?? 'This meeting cannot be retried right now.'),
            onFinish: () => (retrying.value = false),
        },
    );
}

// The work area below opens on the tab that matters most right now.
const workTab = ref(
    props.meeting.status === 'ready_for_review' ? 'drafts' : 'transcript',
);

function showTranscript(): void {
    workTab.value = 'transcript';
    document
        .getElementById('meeting-work')
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const processingText: Record<string, string> = {
    uploaded: 'Recording received. Waiting to start transcription…',
    transcribing: 'Transcribing the recording…',
    transcribed: 'Transcript ready. Starting task extraction…',
    extracting: 'Extracting decisions and proposed tasks…',
};

// Where the meeting is in its life, from the real status and draft counts.
const steps: Step[] = [
    { key: 'transcribed', label: 'Transcribed', hint: 'Audio to text' },
    { key: 'decisions', label: 'Decisions extracted', hint: 'AI analysis' },
    { key: 'reviewed', label: 'Tasks reviewed', hint: 'Human approval' },
    { key: 'execution', label: 'In execution', hint: 'Tasks assigned' },
];

const currentStep = computed(() => {
    const m = props.meeting;
    const c = m.draftCounts;

    if (!m.hasTranscript) {
        return 0;
    }

    if (!(m.status === 'ready_for_review' || m.extractionRun > 0)) {
        return 1;
    }

    if (c.pending > 0 || c.approved + c.rejected === 0) {
        return 2;
    }

    return c.approved > 0 ? 3 : steps.length;
});

// A short, honest preview of the real transcript.
const excerpt = computed(() => {
    const text = (props.transcript ?? '')
        .split(/\n+/)
        .filter((line) => line.trim() !== '')
        .slice(0, 3)
        .join(' ');

    return text.length > 320 ? `${text.slice(0, 320).trimEnd()}…` : text;
});
</script>

<template>
    <Head :title="meeting.title" />

    <PageHeader
        title="Meetings and decisions"
        description="Turn business discussions into clear decisions and executable actions."
    >
        <Button as-child>
            <Link :href="create().url">
                <Plus aria-hidden="true" />
                New meeting
            </Link>
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <MeetingBriefStrip v-if="latestBrief" :brief="latestBrief" />

        <div class="grid items-start gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
            <MeetingsListCard
                :items="sidebar"
                :business-today="businessToday"
                :selected-id="meeting.id"
            />

            <div class="min-w-0 space-y-6">
                <Card class="gap-5 p-5">
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div class="flex min-w-0 items-start gap-4">
                            <span
                                class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-primary-text"
                            >
                                <CalendarDays
                                    class="size-6"
                                    aria-hidden="true"
                                />
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2
                                        class="text-xl/7 font-semibold tracking-tight"
                                    >
                                        {{ meeting.title }}
                                    </h2>
                                    <MeetingStatusBadge
                                        :status="meeting.status"
                                        :label="meeting.statusLabel"
                                    />
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatDate(meeting.meetingDate) }} ·
                                    {{ meeting.department.name }}
                                    <template v-if="meeting.createdBy">
                                        · created by {{ meeting.createdBy }}
                                    </template>
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="meeting.recording.present"
                                variant="outline"
                                as-child
                            >
                                <a href="#recording-heading">
                                    <Mic aria-hidden="true" />
                                    Recording
                                </a>
                            </Button>
                            <Button
                                v-if="meeting.can.reExtract"
                                variant="outline"
                                :disabled="retrying"
                                @click="reExtract"
                            >
                                <Spinner v-if="retrying" />
                                <RefreshCw v-else aria-hidden="true" />
                                Re-extract
                            </Button>
                            <Button
                                v-if="meeting.can.update"
                                variant="outline"
                                as-child
                            >
                                <Link :href="edit(meeting.id).url">
                                    <Pencil aria-hidden="true" />
                                    Edit details
                                </Link>
                            </Button>
                        </div>
                    </div>
                    <StepTracker :steps="steps" :current="currentStep" />
                </Card>

                <Card
                    v-if="processingText[meeting.status]"
                    class="flex-row items-center gap-3 p-4"
                    role="status"
                    aria-live="polite"
                >
                    <Spinner />
                    <p class="text-sm font-medium">
                        {{ processingText[meeting.status] }}
                    </p>
                </Card>
                <p v-else class="sr-only" role="status" aria-live="polite">
                    {{ meeting.statusLabel }}
                </p>

                <Alert
                    v-if="meeting.status === 'failed'"
                    variant="destructive"
                    role="alert"
                >
                    <CircleAlert aria-hidden="true" />
                    <AlertTitle>{{
                        meeting.failureStage === 'transcription'
                            ? 'Transcription failed'
                            : 'Task extraction failed'
                    }}</AlertTitle>
                    <AlertDescription class="space-y-3">
                        <p>{{ meeting.errorMessage }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="meeting.can.retry"
                                size="sm"
                                :disabled="retrying"
                                @click="retryStage"
                            >
                                <Spinner v-if="retrying" />
                                <RefreshCw v-else aria-hidden="true" />
                                Try again
                            </Button>
                            <a
                                v-if="
                                    meeting.failureStage === 'transcription' &&
                                    meeting.can.enterTranscript
                                "
                                href="#manual-transcript"
                                class="text-sm underline"
                                >Paste the transcript instead</a
                            >
                        </div>
                        <p v-if="retryError" class="text-sm" role="alert">
                            {{ retryError }}
                        </p>
                    </AlertDescription>
                </Alert>

                <div class="grid gap-6 2xl:grid-cols-2">
                    <Card class="gap-4 p-5">
                        <SectionHeader
                            title="Meeting decisions"
                            :icon="ClipboardList"
                        >
                            <StatusBadge
                                :label="`${meeting.decisions.length} decisions`"
                                tone="neutral"
                            />
                        </SectionHeader>
                        <EmptyState
                            v-if="meeting.decisions.length === 0"
                            :icon="ClipboardList"
                            message="No decisions were found in this meeting."
                        />
                        <ul v-else class="divide-y text-sm">
                            <li
                                v-for="(decision, i) in meeting.decisions"
                                :key="i"
                                class="flex items-start gap-3 py-2.5"
                            >
                                <CircleCheck
                                    class="mt-0.5 size-5 shrink-0 text-success"
                                    aria-hidden="true"
                                />
                                <span>{{ decision.summary }}</span>
                            </li>
                        </ul>
                    </Card>

                    <Card class="gap-4 p-5">
                        <SectionHeader title="Transcript excerpt" :icon="Quote">
                            <Button
                                v-if="meeting.hasTranscript"
                                variant="ghost"
                                size="sm"
                                @click="showTranscript"
                            >
                                View full transcript
                            </Button>
                        </SectionHeader>
                        <blockquote
                            v-if="excerpt"
                            class="rounded-lg bg-muted/50 p-4 text-sm/6 text-foreground"
                        >
                            {{ excerpt }}
                        </blockquote>
                        <EmptyState
                            v-else
                            :icon="Quote"
                            message="There is no transcript yet. Upload a recording or paste the text below."
                        />
                    </Card>
                </div>

                <DraftsOverviewTable :drafts="taskDrafts" />

                <section id="meeting-work" class="scroll-mt-20">
                    <Card class="gap-0 p-0">
                        <Tabs v-model="workTab">
                            <div class="border-b px-5 pt-4">
                                <TabsList class="max-w-full overflow-x-auto">
                                    <TabsTrigger value="drafts"
                                        >Review drafts</TabsTrigger
                                    >
                                    <TabsTrigger value="decisions"
                                        >Decision quotes</TabsTrigger
                                    >
                                    <TabsTrigger value="transcript"
                                        >Transcript</TabsTrigger
                                    >
                                </TabsList>
                            </div>
                            <div class="p-5">
                                <TabsContent value="drafts">
                                    <TaskDraftsTab
                                        :drafts="taskDrafts"
                                        :counts="meeting.draftCounts"
                                        :review="draftReview"
                                    />
                                </TabsContent>

                                <TabsContent value="decisions">
                                    <section
                                        class="space-y-4"
                                        aria-labelledby="decisions-heading"
                                    >
                                        <SectionHeader
                                            id="decisions-heading"
                                            title="Decisions"
                                            description="What the meeting agreed. Check each quote against the transcript."
                                            :icon="ClipboardList"
                                        >
                                            <StatusBadge
                                                label="AI generated, review before use"
                                                tone="info"
                                            />
                                        </SectionHeader>
                                        <Alert
                                            v-if="meeting.discardedNotice"
                                            role="status"
                                        >
                                            <AlertDescription>{{
                                                meeting.discardedNotice
                                            }}</AlertDescription>
                                        </Alert>
                                        <EmptyState
                                            v-if="
                                                meeting.decisions.length === 0
                                            "
                                            :icon="ClipboardList"
                                            message="No decisions were found in this meeting."
                                        />
                                        <ul v-else class="space-y-3">
                                            <li
                                                v-for="(
                                                    decision, i
                                                ) in meeting.decisions"
                                                :key="i"
                                            >
                                                <Card
                                                    class="gap-2 border-l-4 border-l-info p-5"
                                                >
                                                    <p class="font-medium">
                                                        {{ decision.summary }}
                                                    </p>
                                                    <blockquote
                                                        class="rounded-md bg-muted/50 p-3 text-sm text-muted-foreground"
                                                    >
                                                        {{ decision.excerpt }}
                                                    </blockquote>
                                                </Card>
                                            </li>
                                        </ul>
                                    </section>
                                </TabsContent>

                                <TabsContent value="transcript">
                                    <TranscriptPanel
                                        :meeting="meeting"
                                        :transcript="transcript"
                                        :limits="limits"
                                    />
                                </TabsContent>
                            </div>
                        </Tabs>
                    </Card>
                </section>

                <div class="grid items-start gap-6 2xl:grid-cols-2">
                    <section
                        aria-labelledby="recording-heading"
                        class="space-y-3"
                    >
                        <SectionHeader
                            id="recording-heading"
                            title="Recording"
                            :icon="Mic"
                        />
                        <Card
                            v-if="meeting.recording.present"
                            class="gap-3 p-5"
                        >
                            <p class="text-sm font-medium break-all">
                                {{ meeting.recording.originalName }}
                                <span class="font-normal text-muted-foreground"
                                    >·
                                    {{
                                        formatBytes(meeting.recording.sizeBytes)
                                    }}</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Uploaded
                                {{
                                    formatDateTime(
                                        meeting.recording.uploadedAt,
                                    )
                                }}. Consent confirmed by
                                {{ meeting.recording.consentBy }} on
                                {{
                                    formatDateTime(meeting.recording.consentAt)
                                }}.
                            </p>
                            <audio
                                v-if="meeting.recording.streamUrl"
                                controls
                                preload="none"
                                :src="meeting.recording.streamUrl"
                                class="w-full"
                            >
                                Your browser does not support audio playback.
                            </audio>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-if="meeting.recording.downloadUrl"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                    ><a :href="meeting.recording.downloadUrl"
                                        >Download</a
                                    ></Button
                                >
                                <DeleteRecordingDialog
                                    v-if="meeting.can.deleteRecording"
                                    :meeting-id="meeting.id"
                                />
                            </div>
                        </Card>
                        <Card
                            v-else-if="meeting.recording.deletedAt"
                            class="p-5 text-sm text-muted-foreground"
                        >
                            Recording deleted on
                            {{ formatDateTime(meeting.recording.deletedAt) }}.
                            The transcript and drafts are kept.
                        </Card>
                        <Card v-else
                            ><EmptyState
                                :icon="Mic"
                                message="No recording was uploaded for this meeting."
                        /></Card>

                        <RecordingUploadCard
                            v-if="meeting.can.uploadRecording"
                            :meeting-id="meeting.id"
                            :limits="limits"
                        />
                        <ManualTranscriptForm
                            v-if="meeting.can.enterTranscript"
                            :meeting-id="meeting.id"
                            :limits="limits"
                        />
                    </section>

                    <div class="space-y-6">
                        <Card class="gap-4 p-5">
                            <SectionHeader title="Linked issues" />
                            <ul
                                v-if="meeting.linkedIssues.length"
                                class="space-y-1.5 text-sm"
                            >
                                <li
                                    v-for="issue in meeting.linkedIssues"
                                    :key="issue.id"
                                >
                                    <Link
                                        :href="issue.url"
                                        class="font-medium text-primary-text underline-offset-2 hover:underline"
                                        >{{ issue.title }}</Link
                                    >
                                </li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">
                                No issue is linked to this meeting.
                            </p>
                        </Card>

                        <Alert role="note">
                            <ShieldCheck aria-hidden="true" />
                            <AlertTitle>How your data is handled</AlertTitle>
                            <AlertDescription>
                                Only the recording file is sent to the AI
                                provider for transcription, and only the
                                transcript text, the meeting date, the company
                                time zone and the names of this department's
                                staff are sent for task extraction. The
                                provider's reply is checked by the application
                                and never assigns work. Recordings are deleted
                                automatically
                                {{ meeting.retentionDays }} days after upload;
                                the transcript and drafts are kept.
                            </AlertDescription>
                        </Alert>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
