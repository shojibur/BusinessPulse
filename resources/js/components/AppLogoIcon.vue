<script setup lang="ts">
import { Activity } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <span
        class="inline-flex items-center justify-center rounded-lg bg-primary-strong p-1.5 text-primary-foreground"
        :class="className"
        v-bind="$attrs"
    >
        <Activity class="size-full" aria-hidden="true" />
    </span>
</template>
