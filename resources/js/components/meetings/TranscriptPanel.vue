<script setup lang="ts">
import { FileText } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { useForm } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import { ref } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { update } from '@/routes/meetings/transcript';
import type { MeetingDetail, MeetingLimits } from '@/types';

const props = defineProps<{
    meeting: MeetingDetail;
    transcript: string | null;
    limits: MeetingLimits;
}>();

const editing = ref(false);
const form = useForm({ transcript: props.transcript ?? '' });

function startEdit(): void {
    form.transcript = props.transcript ?? '';
    editing.value = true;
}

function save(): void {
    form.put(update(props.meeting.id).url, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}
</script>

<template>
    <section class="space-y-4" aria-labelledby="transcript-heading">
        <SectionHeader
            id="transcript-heading"
            title="Transcript"
            description="What was said. Review it before relying on the tasks."
            :icon="FileText"
        >
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge
                    v-if="meeting.transcriptSource"
                    :label="
                        meeting.transcriptSource === 'audio'
                            ? 'From audio'
                            : 'Pasted manually'
                    "
                    tone="neutral"
                />
                <StatusBadge
                    v-if="meeting.transcriptEditedAt"
                    :label="`Edited ${formatDateTime(meeting.transcriptEditedAt)}`"
                    tone="info"
                />
            </div>
        </SectionHeader>

        <Alert v-if="meeting.transcriptChangedSinceExtraction" role="status">
            <Info aria-hidden="true" />
            <AlertDescription
                >The transcript was changed after tasks were last extracted, so
                the proposed tasks may not match it.</AlertDescription
            >
        </Alert>

        <Card v-if="!transcript">
            <EmptyState
                :icon="FileText"
                message="There is no transcript yet."
            />
        </Card>

        <Card v-else-if="!editing" class="gap-4 p-6">
            <!-- Plain text only: escaped by Vue, never rendered as HTML. -->
            <p class="text-sm/6 whitespace-pre-wrap">{{ transcript }}</p>
            <div v-if="meeting.can.editTranscript">
                <Button variant="outline" @click="startEdit"
                    >Edit transcript</Button
                >
            </div>
        </Card>

        <Card v-else class="gap-3 p-4">
            <form class="space-y-3" novalidate @submit.prevent="save">
                <div class="grid gap-1.5">
                    <Label for="transcript-edit">Edit transcript</Label>
                    <Textarea
                        id="transcript-edit"
                        v-model="form.transcript"
                        rows="14"
                        :aria-invalid="!!form.errors.transcript"
                    />
                    <p
                        v-if="form.errors.transcript"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.transcript }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing"
                        ><Spinner v-if="form.processing" />Save</Button
                    >
                    <Button
                        type="button"
                        variant="outline"
                        @click="editing = false"
                        >Cancel</Button
                    >
                </div>
            </form>
        </Card>

        <p class="text-xs text-muted-foreground">
            Transcription quality depends on audio quality and language. Review
            the transcript before relying on it.
        </p>
    </section>
</template>
