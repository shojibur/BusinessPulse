<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AiSettingsHelpDialog from '@/components/platform/AiSettingsHelpDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { test, update } from '@/routes/platform/ai';
import type { AiCapabilityStatus, AiSettingsValues } from '@/types';

const props = defineProps<{
    values: AiSettingsValues;
    keys: { text: boolean; transcription: boolean; speech: boolean };
    status: {
        text: AiCapabilityStatus;
        transcription: AiCapabilityStatus;
        speech: AiCapabilityStatus;
    };
    examples: { claude: string[]; anthropic_url: string };
}>();

const form = useForm({
    // A plain string (not the literal union): the union breaks template type-checking of every v-model.
    text_provider: props.values.text_provider as string,
    text_base_url: props.values.text_base_url ?? '',
    text_api_key: '',
    clear_text_api_key: false,
    text_model: props.values.text_model ?? '',
    transcription_base_url: props.values.transcription_base_url ?? '',
    transcription_api_key: '',
    clear_transcription_api_key: false,
    transcription_model: props.values.transcription_model ?? '',
    speech_base_url: props.values.speech_base_url ?? '',
    speech_api_key: '',
    clear_speech_api_key: false,
    speech_model: props.values.speech_model ?? '',
    speech_voice: props.values.speech_voice ?? '',
});

// Bound from the script: a literal https:// inside a template attribute confuses vue-tsc (it reads // as a comment).
const exampleUrl = 'https://api.example.com/v1';

const isClaude = computed(() => form.text_provider === 'anthropic');

// Services that speak the OpenAI audio API. Picking one fills the address; "Other" keeps whatever address is typed.
// (The address and the model names are always yours to change: check the provider's current model list.)
type Preset = { value: string; label: string; url: string };

const openAiUrl = 'https://api.openai.com/v1';
const groqUrl = 'https://api.groq.com/openai/v1';

const transcriptionPresets: Preset[] = [
    { value: 'openai', label: 'OpenAI (Whisper)', url: openAiUrl },
    { value: 'groq', label: 'Groq (Whisper)', url: groqUrl },
    { value: 'custom', label: 'Other OpenAI-compatible service', url: '' },
];

const speechPresets: Preset[] = [
    { value: 'openai', label: 'OpenAI', url: openAiUrl },
    { value: 'custom', label: 'Other OpenAI-compatible service', url: '' },
];

const presetOf = (url: string, presets: Preset[]): string =>
    presets.find((preset) => preset.url !== '' && preset.url === url.trim())
        ?.value ?? 'custom';

const transcriptionPreset = computed(() =>
    presetOf(form.transcription_base_url, transcriptionPresets),
);

const speechPreset = computed(() =>
    presetOf(form.speech_base_url, speechPresets),
);

function pickTranscriptionPreset(value: unknown): void {
    const preset = transcriptionPresets.find((p) => p.value === value);

    if (preset && preset.url !== '') {
        form.transcription_base_url = preset.url;
    }
}

function pickSpeechPreset(value: unknown): void {
    const preset = speechPresets.find((p) => p.value === value);

    if (preset && preset.url !== '') {
        form.speech_base_url = preset.url;
    }
}

const sourceLabel = (s: AiCapabilityStatus): string =>
    s.source === 'platform'
        ? 'Key saved here'
        : s.source === 'env'
          ? 'Key from .env'
          : 'No key';

function save(): void {
    form.put(update().url, {
        preserveScroll: true,
        // Keys are write-only: never keep them in the form after a save.
        onFinish: () =>
            form.reset(
                'text_api_key',
                'transcription_api_key',
                'speech_api_key',
            ),
    });
}

function testConnection(): void {
    router.post(test().url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="AI settings" />

    <PageHeader
        title="AI settings"
        description="One provider setup for the whole site. Every company uses it."
    >
        <AiSettingsHelpDialog :claude-models="examples.claude" />
    </PageHeader>

    <Card class="mb-6 gap-2 p-5">
        <SectionHeader title="Status" />
        <ul class="divide-y text-sm">
            <li
                v-for="row in [
                    { label: 'Text (brief and tasks)', s: status.text },
                    { label: 'Transcription', s: status.transcription },
                    { label: 'Voice', s: status.speech },
                ]"
                :key="row.label"
                class="flex flex-wrap items-center justify-between gap-2 py-2"
            >
                <span class="font-medium">{{ row.label }}</span>
                <span class="flex flex-wrap items-center gap-2">
                    <span class="text-muted-foreground"
                        >{{
                            row.s.provider === 'anthropic'
                                ? 'Claude'
                                : 'OpenAI-compatible'
                        }}
                        · {{ row.s.model ?? 'no model' }} ·
                        {{ sourceLabel(row.s) }}</span
                    >
                    <StatusBadge
                        :label="row.s.configured ? 'Ready' : 'Not set up'"
                        :tone="row.s.configured ? 'success' : 'warning'"
                    />
                </span>
            </li>
        </ul>
    </Card>

    <form class="space-y-6" novalidate @submit.prevent="save">
        <Card class="gap-4 p-5">
            <SectionHeader title="Text" />
            <div class="grid gap-2 sm:max-w-xs">
                <Label for="text_provider">Provider</Label>
                <NativeSelect id="text_provider" v-model="form.text_provider">
                    <option value="openai">OpenAI-compatible</option>
                    <option value="anthropic">Claude (Anthropic)</option>
                </NativeSelect>
            </div>
            <div class="grid gap-2">
                <Label for="text_base_url">Address (base URL)</Label>
                <Input
                    id="text_base_url"
                    v-model="form.text_base_url"
                    :placeholder="
                        isClaude ? examples.anthropic_url : exampleUrl
                    "
                />
                <p
                    v-if="form.errors.text_base_url"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.text_base_url }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="text_api_key">API key</Label>
                <Input
                    id="text_api_key"
                    v-model="form.text_api_key"
                    type="password"
                    autocomplete="off"
                    :placeholder="
                        keys.text
                            ? 'Saved. Leave empty to keep it.'
                            : 'Paste the key'
                    "
                />
                <label v-if="keys.text" class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        class="size-4"
                        :checked="form.clear_text_api_key"
                        @change="
                            form.clear_text_api_key = (
                                $event.target as HTMLInputElement
                            ).checked
                        "
                    />
                    Remove the saved key
                </label>
            </div>
            <div class="grid gap-2">
                <Label for="text_model">Model</Label>
                <Input
                    id="text_model"
                    v-model="form.text_model"
                    :placeholder="isClaude ? examples.claude[1] : 'model name'"
                />
                <p
                    v-if="form.errors.text_model"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.text_model }}
                </p>
            </div>
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="testConnection"
                    >Test text connection (save first)</Button
                >
            </div>
        </Card>

        <Card class="gap-4 p-5">
            <SectionHeader title="Transcription" />
            <p class="text-sm text-muted-foreground">
                Any service that offers the OpenAI-style speech-to-text API.
                Pick one to fill in its address, or choose Other and type your
                own.
            </p>
            <div class="grid gap-2 sm:max-w-xs">
                <Label for="transcription_preset">Service</Label>
                <NativeSelect
                    id="transcription_preset"
                    :model-value="transcriptionPreset"
                    @update:model-value="pickTranscriptionPreset"
                >
                    <option
                        v-for="preset in transcriptionPresets"
                        :key="preset.value"
                        :value="preset.value"
                    >
                        {{ preset.label }}
                    </option>
                </NativeSelect>
            </div>
            <div class="grid gap-2">
                <Label for="transcription_base_url">Address (base URL)</Label>
                <Input
                    id="transcription_base_url"
                    v-model="form.transcription_base_url"
                    :placeholder="exampleUrl"
                />
                <p
                    v-if="form.errors.transcription_base_url"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.transcription_base_url }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="transcription_api_key">API key</Label>
                <Input
                    id="transcription_api_key"
                    v-model="form.transcription_api_key"
                    type="password"
                    autocomplete="off"
                    :placeholder="
                        keys.transcription
                            ? 'Saved. Leave empty to keep it.'
                            : 'Paste the key'
                    "
                />
                <label
                    v-if="keys.transcription"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4"
                        :checked="form.clear_transcription_api_key"
                        @change="
                            form.clear_transcription_api_key = (
                                $event.target as HTMLInputElement
                            ).checked
                        "
                    />
                    Remove the saved key
                </label>
            </div>
            <div class="grid gap-2">
                <Label for="transcription_model">Model</Label>
                <Input
                    id="transcription_model"
                    v-model="form.transcription_model"
                />
                <p
                    v-if="form.errors.transcription_model"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.transcription_model }}
                </p>
            </div>
        </Card>

        <Card class="gap-4 p-5">
            <SectionHeader title="Voice" />
            <p class="text-sm text-muted-foreground">
                Any service that offers the OpenAI-style text-to-speech API.
                Pick one to fill in its address, or choose Other and type your
                own.
            </p>
            <div class="grid gap-2 sm:max-w-xs">
                <Label for="speech_preset">Service</Label>
                <NativeSelect
                    id="speech_preset"
                    :model-value="speechPreset"
                    @update:model-value="pickSpeechPreset"
                >
                    <option
                        v-for="preset in speechPresets"
                        :key="preset.value"
                        :value="preset.value"
                    >
                        {{ preset.label }}
                    </option>
                </NativeSelect>
            </div>
            <div class="grid gap-2">
                <Label for="speech_base_url">Address (base URL)</Label>
                <Input
                    id="speech_base_url"
                    v-model="form.speech_base_url"
                    :placeholder="exampleUrl"
                />
                <p
                    v-if="form.errors.speech_base_url"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.speech_base_url }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="speech_api_key">API key</Label>
                <Input
                    id="speech_api_key"
                    v-model="form.speech_api_key"
                    type="password"
                    autocomplete="off"
                    :placeholder="
                        keys.speech
                            ? 'Saved. Leave empty to keep it.'
                            : 'Paste the key'
                    "
                />
                <label
                    v-if="keys.speech"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4"
                        :checked="form.clear_speech_api_key"
                        @change="
                            form.clear_speech_api_key = (
                                $event.target as HTMLInputElement
                            ).checked
                        "
                    />
                    Remove the saved key
                </label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="speech_model">Model</Label>
                    <Input id="speech_model" v-model="form.speech_model" />
                    <p
                        v-if="form.errors.speech_model"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.speech_model }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="speech_voice">Voice</Label>
                    <Input id="speech_voice" v-model="form.speech_voice" />
                    <p
                        v-if="form.errors.speech_voice"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.speech_voice }}
                    </p>
                </div>
            </div>
        </Card>

        <Button type="submit" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            Save AI settings
        </Button>
    </form>
</template>
