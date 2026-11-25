<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { ErrorStateProps } from '@/types';

withDefaults(defineProps<ErrorStateProps>(), {
    retryLabel: 'Try again',
});

defineEmits<{ retry: [] }>();
</script>

<template>
    <div
        role="alert"
        class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center"
    >
        <component
            :is="icon ?? CircleAlert"
            class="size-8 text-severity-high"
            aria-hidden="true"
        />
        <p class="max-w-md text-sm text-foreground">{{ message }}</p>
        <Button variant="outline" size="sm" @click="$emit('retry')">
            {{ retryLabel }}
        </Button>
    </div>
</template>
