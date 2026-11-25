<script setup lang="ts">
import { ArrowDown, ArrowUp, Minus } from '@lucide/vue';
import { computed } from 'vue';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { KpiCardProps } from '@/types';

const props = defineProps<KpiCardProps>();

const changeIcon = computed(() => {
    switch (props.change?.direction) {
        case 'up':
            return ArrowUp;
        case 'down':
            return ArrowDown;
        default:
            return Minus;
    }
});

const changeLabel = computed(() => {
    switch (props.change?.direction) {
        case 'up':
            return 'Increase';
        case 'down':
            return 'Decrease';
        default:
            return 'No change';
    }
});
</script>

<template>
    <Card class="gap-2 p-4">
        <p class="text-xs/4 font-medium text-muted-foreground">{{ label }}</p>

        <div v-if="loading" class="space-y-2" aria-busy="true">
            <Skeleton class="h-9 w-24" />
            <Skeleton class="h-4 w-32" />
            <span class="sr-only">Loading {{ label }}</span>
        </div>

        <template v-else>
            <p class="text-[28px]/9 font-semibold text-foreground tabular-nums">
                {{ value }}
            </p>
            <p
                v-if="change"
                class="flex items-center gap-1 text-xs/4 font-medium text-foreground"
            >
                <component
                    :is="changeIcon"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <span class="sr-only">{{ changeLabel }}: </span>
                <span class="tabular-nums">{{ change.text }}</span>
            </p>
            <p v-if="caption" class="text-xs/4 text-muted-foreground">
                {{ caption }}
            </p>
        </template>
    </Card>
</template>
