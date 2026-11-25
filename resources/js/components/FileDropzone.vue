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
        class="flex flex-col items-center gap-3 rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors"
        :class="[
            dragging ? 'border-primary bg-accent' : 'border-input bg-surface',
            invalid && 'border-severity-high',
        ]"
        @dragover.prevent="dragging = true"
        @dragleave="dragging = false"
        @drop.prevent="drop"
    >
        <FileUp class="size-8 text-muted-foreground" aria-hidden="true" />

        <p v-if="file" class="text-sm font-medium break-all">
            {{ file.name }}
            <span class="font-normal text-muted-foreground">
                ({{ formatBytes(file.size) }})
            </span>
        </p>
        <p v-else class="text-sm text-muted-foreground">
            Drag a CSV file here, or choose one.
        </p>

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
