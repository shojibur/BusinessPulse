<script setup lang="ts">
import { Check } from '@lucide/vue';

export type Step = {
    key: string;
    label: string;
    /** A short second line (for example "Audio → text"). */
    hint?: string;
};

/**
 * A horizontal progress tracker: steps before the current one are done (check), the current one is highlighted, and
 * the rest are quiet. `current` is the index of the active step; use steps.length for "all done".
 */
defineProps<{ steps: Step[]; current: number }>();
</script>

<template>
    <ol class="flex w-full items-start" role="list">
        <li
            v-for="(step, index) in steps"
            :key="step.key"
            class="relative flex min-w-0 flex-1 flex-col items-center text-center"
            :aria-current="index === current ? 'step' : undefined"
        >
            <span
                v-if="index > 0"
                class="absolute top-4 right-1/2 h-0.5 w-full"
                :class="index <= current ? 'bg-primary' : 'bg-border'"
                aria-hidden="true"
            />
            <span
                class="relative z-10 flex size-8 items-center justify-center rounded-full text-xs font-semibold ring-4 ring-card"
                :class="
                    index < current
                        ? 'bg-primary text-primary-foreground'
                        : index === current
                          ? 'border-2 border-primary bg-card text-primary-text'
                          : 'border bg-muted text-muted-foreground'
                "
            >
                <Check
                    v-if="index < current"
                    class="size-4"
                    aria-hidden="true"
                />
                <template v-else>{{ index + 1 }}</template>
            </span>
            <span
                class="mt-2 px-1 text-xs/4 font-medium"
                :class="
                    index <= current
                        ? 'text-foreground'
                        : 'text-muted-foreground'
                "
                >{{ step.label }}</span
            >
            <span
                v-if="step.hint"
                class="hidden px-1 text-[11px]/4 text-muted-foreground sm:block"
                >{{ step.hint }}</span
            >
        </li>
    </ol>
</template>
