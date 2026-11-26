<script setup lang="ts">
export type TimelineItem = {
    key: string | number;
    title: string;
    meta?: string;
    detail?: string;
    tone?: 'neutral' | 'danger' | 'success' | 'warning' | 'info';
};

defineProps<{ items: TimelineItem[] }>();

const dots = {
    neutral: 'bg-slate-400',
    danger: 'bg-severity-high',
    success: 'bg-success',
    warning: 'bg-severity-medium',
    info: 'bg-info',
};
</script>

<template>
    <ol class="relative ms-1.5 space-y-5 border-s border-border">
        <li v-for="item in items" :key="item.key" class="ms-5">
            <span
                class="absolute -start-[6px] mt-1.5 size-3 rounded-full ring-4 ring-card"
                :class="dots[item.tone ?? 'neutral']"
                aria-hidden="true"
            />
            <p class="text-sm/5 font-medium text-foreground">
                {{ item.title }}
            </p>
            <p v-if="item.meta" class="text-xs/4 text-muted-foreground">
                {{ item.meta }}
            </p>
            <p v-if="item.detail" class="mt-1 text-xs/4 text-foreground">
                {{ item.detail }}
            </p>
        </li>
    </ol>
</template>
