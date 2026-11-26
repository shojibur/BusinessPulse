<script setup lang="ts">
import { FileUp } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatBytes } from '@/lib/format';

withDefaults(
    defineProps<{
        accept?: string;
        file: File | null;
        disabled?: boolean;
        invalid?: boolean;
    }>(),
    { accept: '.csv' },
);

const emit = defineEmits<{ select: [file: File | null] }>();

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

function choose(files: FileList | null): void {
    emit('select', files?.item(0) ?? null);
}

function drop(event: DragEvent): void {
    dragging.value = false;
    choose(event.dataTransfer?.files ?? null);
}
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed px-6 py-12 text-center transition-colors"
        :class="[
            dragging
                ? 'border-primary bg-accent'
                : file
                  ? 'border-primary/40 bg-primary/5'
                  : 'border-input bg-card hover:border-primary/40',
            invalid && 'border-severity-high',
        ]"
        @dragover.prevent="dragging = true"
        @dragleave="dragging = false"
        @drop.prevent="drop"
    >
        <span
            class="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary-text"
        >
            <FileUp class="size-7" aria-hidden="true" />
        </span>

        <p v-if="file" class="text-sm font-medium break-all">
            {{ file.name }}
            <span class="font-normal text-muted-foreground">
                ({{ formatBytes(file.size) }})
            </span>
        </p>
        <template v-else>
            <p class="text-base font-semibold">Drop your CSV file here</p>
            <p class="text-sm text-muted-foreground">
                or choose it from your computer
            </p>
        </template>

        <!-- A real file input keeps the control keyboard and screen reader operable. -->
        <input
            id="sales-file"
            ref="input"
            type="file"
            class="sr-only"
            :accept="accept"
            :disabled="disabled"
            aria-label="Sales CSV file"
            @change="choose(($event.target as HTMLInputElement).files)"
        />
        <Button
            type="button"
            variant="outline"
            :disabled="disabled"
            @click="input?.click()"
        >
            {{ file ? 'Choose a different file' : 'Choose file' }}
        </Button>
    </div>
</template>
