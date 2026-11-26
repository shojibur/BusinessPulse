<script setup lang="ts">
import type { Component } from 'vue';
import { Card } from '@/components/ui/card';

withDefaults(
    defineProps<{
        label: string;
        value: string;
        caption?: string;
        icon?: Component;
        tone?: 'neutral' | 'danger' | 'success' | 'warning' | 'info';
    }>(),
    { tone: 'neutral' },
);

const iconTones = {
    neutral: 'bg-muted text-muted-foreground',
    danger: 'bg-severity-high-bg text-severity-high',
    success: 'bg-success-bg text-success',
    warning: 'bg-severity-medium-bg text-severity-medium',
    info: 'bg-info-bg text-info',
};

const valueTones = {
    neutral: 'text-foreground',
    danger: 'text-severity-high',
    success: 'text-success',
    warning: 'text-severity-medium',
    info: 'text-info',
};
</script>

<template>
    <Card class="gap-2 p-5">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs/4 font-medium text-muted-foreground">
                {{ label }}
            </p>
            <span
                v-if="icon"
                class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                :class="iconTones[tone]"
            >
                <component :is="icon" class="size-4" aria-hidden="true" />
            </span>
        </div>
        <p
            class="text-[26px]/8 font-semibold tracking-tight tabular-nums"
            :class="valueTones[tone]"
        >
            {{ value }}
        </p>
        <p v-if="caption" class="text-xs/4 text-muted-foreground">
            {{ caption }}
        </p>
    </Card>
</template>
