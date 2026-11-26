<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    /** One short line under the title. Keep long explanations out of the page. */
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span
                v-if="icon"
                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary-text"
            >
                <component :is="icon" class="size-4" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <h2 class="text-base/6 font-semibold text-foreground">
                    {{ title }}
                </h2>
                <p v-if="description" class="text-xs/4 text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>
        <div v-if="$slots.default" class="flex items-center gap-2">
            <slot />
        </div>
    </div>
</template>
