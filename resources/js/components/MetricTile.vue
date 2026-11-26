<script setup lang="ts">
import type { Component } from 'vue';
import { Card } from '@/components/ui/card';

withDefaults(
    defineProps<{
        label: string;
        value: string;
        /** A small line under the value (a period, a comparison). */
        caption?: string;
        icon?: Component;
        /** Colours the icon tile and the value. */
        tone?: 'neutral' | 'danger' | 'success' | 'warning' | 'info';
        /** A pill next to the value, for a change such as "+20.97%". */
        chip?: string;
        chipTone?: 'neutral' | 'danger' | 'success' | 'warning' | 'info';
    }>(),
    { tone: 'neutral', chipTone: 'neutral' },
);

const iconTones = {
    neutral: 'bg-teal-50 text-primary-text',
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

const chipTones = {
    neutral: 'bg-muted text-muted-foreground',
    danger: 'bg-severity-high-bg text-severity-high',
    success: 'bg-success-bg text-success',
    warning: 'bg-severity-medium-bg text-severity-medium',
    info: 'bg-info-bg text-info',
};
</script>

<template>
    <Card class="flex-row items-center gap-4 p-5">
        <span
            v-if="icon"
            class="flex size-14 shrink-0 items-center justify-center rounded-2xl"
            :class="iconTones[tone]"
        >
            <component :is="icon" class="size-7" aria-hidden="true" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm/5 font-medium text-muted-foreground">
                {{ label }}
            </p>
            <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                <p
                    class="text-[28px]/9 font-semibold tracking-tight tabular-nums"
                    :class="valueTones[tone]"
                >
                    {{ value }}
                </p>
                <span
                    v-if="chip"
                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums"
                    :class="chipTones[chipTone]"
                    >{{ chip }}</span
                >
            </div>
            <p v-if="caption" class="truncate text-xs/4 text-muted-foreground">
                {{ caption }}
            </p>
        </div>
    </Card>
</template>
