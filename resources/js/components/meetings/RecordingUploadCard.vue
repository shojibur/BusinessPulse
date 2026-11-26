<script setup lang="ts">
import { FileUp } from '@lucide/vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/meetings/recording';
import type { MeetingLimits } from '@/types';

const props = defineProps<{ meetingId: number; limits: MeetingLimits }>();

const form = useForm<{ recording_consent: boolean; file: File | null }>({
    recording_consent: false,
    file: null,
});
const sizeError = ref<string | null>(null);

function pick(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    sizeError.value = null;

    if (file && file.size > props.limits.maxUploadMb * 1024 * 1024) {
        sizeError.value = `The recording must be ${props.limits.maxUploadMb} MB or smaller.`;
        form.file = null;

        return;
    }

    form.file = file;
}

function submit(): void {
    form.post(store(props.meetingId).url, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Card class="gap-4 p-5">
        <SectionHeader
            title="Upload a recording"
            description="We transcribe it and propose tasks from it."
            :icon="FileUp"
        />
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <label
                class="flex items-start gap-3 rounded-lg border bg-muted/30 p-3 text-sm"
            >
                <input
                    v-model="form.recording_consent"
                    type="checkbox"
                    class="mt-1 size-4"
                />
                <span>
                    I have permission from the meeting participants to record
                    this meeting and to send the audio to an external AI
                    provider for transcription.
                </span>
            </label>
            <p
                v-if="form.errors.recording_consent"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ form.errors.recording_consent }}
            </p>

            <div class="grid gap-1.5">
                <Label for="recording-file">Recording file</Label>
                <Input
                    id="recording-file"
                    type="file"
                    :accept="limits.extensions.map((e) => `.${e}`).join(',')"
                    @change="pick"
                />
                <p class="text-xs text-muted-foreground">
                    {{ limits.extensions.join(', ') }} up to
                    {{ limits.maxUploadMb }} MB, about
                    {{ limits.intendedMinutes }} minutes. Transcription works
                    best for clear English audio; other languages, mixed
                    languages and noisy rooms may be inaccurate. No translation
                    is performed.
                </p>
                <p
                    v-if="sizeError || form.errors.file"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ sizeError ?? form.errors.file }}
                </p>
            </div>

            <Progress
                v-if="form.progress"
                :model-value="form.progress.percentage"
                aria-label="Upload progress"
            />

            <Button
                type="submit"
                :disabled="
                    form.processing || !form.file || !form.recording_consent
                "
            >
                <Spinner v-if="form.processing" />
                Upload and transcribe
            </Button>
        </form>
    </Card>
</template>
