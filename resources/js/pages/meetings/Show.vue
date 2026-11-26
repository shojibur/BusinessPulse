<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    CircleAlert,
    CircleCheck,
    CircleX,
    ClipboardList,
    ListChecks,
    Mic,
    RefreshCw,
    ShieldCheck,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import DeleteRecordingDialog from '@/components/meetings/DeleteRecordingDialog.vue';
import ManualTranscriptForm from '@/components/meetings/ManualTranscriptForm.vue';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import RecordingUploadCard from '@/components/meetings/RecordingUploadCard.vue';
import TaskDraftsTab from '@/components/meetings/TaskDraftsTab.vue';
import TranscriptPanel from '@/components/meetings/TranscriptPanel.vue';
import MetricTile from '@/components/MetricTile.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatBytes, formatDate, formatDateTime } from '@/lib/format';
import { edit, index, retry } from '@/routes/meetings';
import type {
    DraftReview,
    MeetingDetail,
    MeetingLimits,
    TaskDraftSummary,
} from '@/types';

const props = defineProps<{
    meeting: MeetingDetail;
    transcript: string | null;
    taskDrafts: TaskDraftSummary[];
    draftReview: DraftReview | null;
    limits: MeetingLimits;
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

// Open on the tab that matters most right now.
const defaultTab = computed(() =>
    props.meeting.status === 'ready_for_review' ? 'drafts' : 'transcript',
);

const processingText: Record<string, string> = {
    uploaded: 'Recording received. Waiting to start transcription…',
    transcribing: 'Transcribing the recording…',
    transcribed: 'Transcript ready. Starting task extraction…',
    extracting: 'Extracting decisions and proposed tasks…',
};
</script>

<template>
    <Head :title="meeting.title" />

    <PageHeader
        :title="meeting.title"
        :description="`${meeting.department.name} · ${formatDate(meeting.meetingDate)}`"
    >
        <MeetingStatusBadge
            :status="meeting.status"
            :label="meeting.statusLabel"
        />
        <Button v-if="meeting.can.update" variant="outline" as-child>
            <Link :href="edit(meeting.id).url">Edit details</Link>
        </Button>
        <Button variant="outline" as-child
            ><Link :href="index().url">
                <ChevronLeft aria-hidden="true" />
                All meetings
            </Link></Button
        >
    </PageHeader>

    <div class="space-y-6">
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

        <!-- The headline numbers first. -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricTile
                label="Decisions"
                :value="String(meeting.decisions.length)"
                caption="Found in the discussion"
                :icon="ClipboardList"
                tone="info"
            />
            <MetricTile
                label="Pending drafts"
                :value="String(meeting.draftCounts.pending)"
                caption="Waiting for your review"
                :icon="ListChecks"
                :tone="meeting.draftCounts.pending > 0 ? 'warning' : 'neutral'"
            />
            <MetricTile
                label="Approved tasks"
                :value="String(meeting.draftCounts.approved)"
                caption="Assigned to people"
                :icon="CircleCheck"
                :tone="meeting.draftCounts.approved > 0 ? 'success' : 'neutral'"
            />
            <MetricTile
                label="Rejected"
                :value="String(meeting.draftCounts.rejected)"
                caption="Kept in the history"
                :icon="CircleX"
            />
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <!-- Main column: the transcript, decisions and proposed tasks. -->
            <div class="min-w-0">
                <Tabs :default-value="defaultTab">
                    <TabsList class="max-w-full overflow-x-auto">
                        <TabsTrigger value="drafts">Task drafts</TabsTrigger>
                        <TabsTrigger value="decisions">Decisions</TabsTrigger>
                        <TabsTrigger value="transcript">Transcript</TabsTrigger>
                    </TabsList>

                    <TabsContent value="drafts" class="mt-4">
                        <div v-if="meeting.can.reExtract" class="mb-4">
                            <Button
                                variant="outline"
                                :disabled="retrying"
                                @click="reExtract"
                            >
                                <Spinner v-if="retrying" />
                                <RefreshCw v-else aria-hidden="true" />
                                Re-extract tasks from the current transcript
                            </Button>
                        </div>
                        <TaskDraftsTab
                            :drafts="taskDrafts"
                            :counts="meeting.draftCounts"
                            :review="draftReview"
                        />
                    </TabsContent>

                    <TabsContent value="decisions" class="mt-4">
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
                            <Alert v-if="meeting.discardedNotice" role="status">
                                <AlertDescription>{{
                                    meeting.discardedNotice
                                }}</AlertDescription>
                            </Alert>
                            <Card v-if="meeting.decisions.length === 0">
                                <EmptyState
                                    :icon="ClipboardList"
                                    message="No decisions were found in this meeting."
                                />
                            </Card>
                            <ul v-else class="space-y-3">
                                <li
                                    v-for="(decision, i) in meeting.decisions"
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

                    <TabsContent value="transcript" class="mt-4">
                        <TranscriptPanel
                            :meeting="meeting"
                            :transcript="transcript"
                            :limits="limits"
                        />
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Side column: the facts and the recording. -->
            <aside class="min-w-0 space-y-6">
                <Card class="gap-4 p-5">
                    <SectionHeader title="Details" />
                    <dl class="grid gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Created by
                            </dt>
                            <dd class="font-medium">{{ meeting.createdBy }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Linked issues
                            </dt>
                            <dd
                                v-if="meeting.linkedIssues.length"
                                class="font-medium"
                            >
                                <Link
                                    v-for="issue in meeting.linkedIssues"
                                    :key="issue.id"
                                    :href="issue.url"
                                    class="block text-primary-text underline-offset-2 hover:underline"
                                    >{{ issue.title }}</Link
                                >
                            </dd>
                            <dd v-else class="text-muted-foreground">None</dd>
                        </div>
                    </dl>
                </Card>

                <section aria-labelledby="recording-heading" class="space-y-3">
                    <SectionHeader
                        id="recording-heading"
                        title="Recording"
                        :icon="Mic"
                    />
                    <Card v-if="meeting.recording.present" class="gap-3 p-5">
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
                            {{ formatDateTime(meeting.recording.uploadedAt) }}.
                            Consent confirmed by
                            {{ meeting.recording.consentBy }} on
                            {{ formatDateTime(meeting.recording.consentAt) }}.
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
                        {{ formatDateTime(meeting.recording.deletedAt) }}. The
                        transcript and drafts are kept.
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

                <Alert role="note">
                    <ShieldCheck aria-hidden="true" />
                    <AlertTitle>How your data is handled</AlertTitle>
                    <AlertDescription>
                        Only the recording file is sent to the AI provider for
                        transcription, and only the transcript text, the meeting
                        date, the company time zone and the names of this
                        department's staff are sent for task extraction. The
                        provider's reply is checked by the application and never
                        assigns work. Recordings are deleted automatically
                        {{ meeting.retentionDays }} days after upload; the
                        transcript and drafts are kept.
                    </AlertDescription>
                </Alert>
            </aside>
        </div>
    </div>
</template>
