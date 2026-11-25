<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { EmptyStateProps } from '@/types';

defineProps<EmptyStateProps>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center"
    >
        <component
            :is="icon ?? Inbox"
            class="size-8 text-muted-foreground"
            aria-hidden="true"
        />
        <p class="max-w-md text-sm text-muted-foreground">{{ message }}</p>
        <div v-if="$slots.default" class="mt-1">
            <slot />
        </div>
    </div>
</template>
