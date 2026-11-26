<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { EmptyStateProps } from '@/types';

defineProps<EmptyStateProps>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center"
    >
        <span
            class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
            <component :is="icon ?? Inbox" class="size-6" aria-hidden="true" />
        </span>
        <p class="max-w-md text-sm text-muted-foreground">{{ message }}</p>
        <div v-if="$slots.default" class="mt-1">
            <slot />
        </div>
    </div>
</template>
