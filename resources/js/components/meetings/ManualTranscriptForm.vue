<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/meetings/transcript';
import type { MeetingLimits } from '@/types';

const props = defineProps<{ meetingId: number; limits: MeetingLimits }>();
const form = useForm({ transcript: '' });

function submit(): void {
    form.post(store(props.meetingId).url, { preserveScroll: true });
}
</script>

<template>
    <Card id="manual-transcript" class="gap-4 p-4">
        <h3 class="text-base font-semibold">Paste a transcript instead</h3>
        <form class="space-y-3" novalidate @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="manual-transcript-text"
                    >Transcript ({{ limits.transcriptMin }} to
                    {{ limits.transcriptMax.toLocaleString('en-MY') }}
                    characters)</Label
                >
                <Textarea
                    id="manual-transcript-text"
                    v-model="form.transcript"
                    rows="8"
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
            <Button
                type="submit"
                :disabled="
                    form.processing ||
                    form.transcript.trim().length < limits.transcriptMin
                "
            >
                <Spinner v-if="form.processing" />
                Save transcript and extract tasks
            </Button>
        </form>
    </Card>
</template>
