<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{ name: string; size?: 'sm' | 'md' }>(),
    { size: 'md' },
);

const initials = computed(
    () =>
        props.name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || '?',
);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary-text"
        :class="size === 'sm' ? 'size-6 text-[10px]' : 'size-8 text-xs'"
        aria-hidden="true"
        >{{ initials }}</span
    >
</template>
