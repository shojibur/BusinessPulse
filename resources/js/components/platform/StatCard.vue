<script setup lang="ts">
import type { Component } from 'vue';
import { Card } from '@/components/ui/card';

defineProps<{
    label: string;
    value: number | string;
    icon: Component;
    /** Colours the icon tile. */
    tone?: 'primary' | 'success' | 'warning' | 'info';
    caption?: string;
}>();

const tones = {
    primary: 'bg-primary/10 text-primary-text',
    success: 'bg-success-bg text-success',
    warning: 'bg-severity-medium-bg text-severity-medium',
    info: 'bg-info-bg text-info',
};
</script>

<template>
    <Card class="flex-row items-center gap-4 p-5">
        <span
            class="flex size-11 shrink-0 items-center justify-center rounded-lg"
            :class="tones[tone ?? 'primary']"
        >
            <component :is="icon" class="size-5" aria-hidden="true" />
        </span>
        <div class="min-w-0">
            <p class="text-xs/4 font-medium text-muted-foreground">
                {{ label }}
            </p>
            <p class="text-2xl/8 font-semibold text-foreground tabular-nums">
                {{ value }}
            </p>
            <p v-if="caption" class="truncate text-xs/4 text-muted-foreground">
                {{ caption }}
            </p>
        </div>
    </Card>
</template>
