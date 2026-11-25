<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Volume2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { AudioView } from '@/types';

const props = defineProps<{ audio: AudioView; requestUrl: string }>();

const requesting = ref(false);
const error = ref<string | null>(null);
const inProgress = computed(
    () =>
        props.audio.status === 'queued' || props.audio.status === 'generating',
);

function request(): void {
    requesting.value = true;
    error.value = null;
    router.post(
        props.requestUrl,
        {},
        {
            preserveScroll: true,
            onError: (bag) =>
                (error.value =
                    Object.values(bag)[0] ?? 'Audio could not be requested.'),
            onFinish: () => (requesting.value = false),
        },
    );
}

// Poll every 3 seconds while the audio is being prepared.
let timer: ReturnType<typeof setInterval> | null = null;

watch(
    inProgress,
    (active) => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }

        if (active) {
            timer = setInterval(() => router.reload({ only: ['audio'] }), 3000);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => timer !== null && clearInterval(timer));
</script>

<template>
    <section aria-labelledby="audio-heading" class="space-y-3">
        <h2
            id="audio-heading"
            class="flex items-center gap-2 text-base font-semibold"
        >
            <Volume2 class="size-4" aria-hidden="true" />
            Voice brief
        </h2>

        <p
            v-if="!audio.configured && audio.status !== 'ready'"
            class="text-sm text-muted-foreground"
        >
            Voice is not configured.
        </p>

        <template v-else>
            <audio
                v-if="audio.status === 'ready' && audio.url"
                controls
                preload="none"
                :src="audio.url"
                class="w-full"
            >
                Your browser does not support audio playback.
            </audio>

            <p
                v-else-if="inProgress"
                class="flex items-center gap-2 text-sm text-muted-foreground"
                role="status"
            >
                <Spinner />
                {{
                    audio.status === 'queued'
                        ? 'Audio is queued…'
                        : 'Preparing audio…'
                }}
            </p>

            <div v-else-if="audio.can_request" class="space-y-2">
                <Alert
                    v-if="audio.status === 'failed'"
                    variant="destructive"
                    role="alert"
                >
                    <AlertDescription
                        >Audio could not be created. You can try
                        again.</AlertDescription
                    >
                </Alert>
                <Button
                    variant="outline"
                    :disabled="requesting"
                    @click="request"
                >
                    <Spinner v-if="requesting" />
                    {{
                        audio.status === 'failed'
                            ? 'Retry audio'
                            : 'Create audio'
                    }}
                </Button>
            </div>
        </template>

        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>
    </section>
</template>
