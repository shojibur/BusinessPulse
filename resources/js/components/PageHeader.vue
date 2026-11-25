<script setup lang="ts">
import type { PageHeaderProps } from '@/types';

defineProps<PageHeaderProps>();
</script>

<template>
    <header
        class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0">
            <h1 class="text-2xl/8 font-semibold text-foreground">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div
            v-if="$slots.default"
            class="flex shrink-0 flex-wrap items-center gap-2"
        >
            <slot />
        </div>
    </header>
</template>
