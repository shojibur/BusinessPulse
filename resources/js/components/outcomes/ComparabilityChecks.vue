<script setup lang="ts">
import { CircleCheck, CircleX, Minus } from '@lucide/vue';

defineProps<{ checks: { name: string; label: string; state: string }[] }>();

const stateText: Record<string, string> = {
    passed: 'Passed',
    failed: 'Failed',
    skipped: 'Not checked',
    not_applicable: 'Not applicable',
};
</script>

<template>
    <ul class="divide-y text-sm">
        <li
            v-for="check in checks"
            :key="check.name"
            class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0"
        >
            <CircleCheck
                v-if="check.state === 'passed'"
                class="size-5 shrink-0 text-success"
                aria-hidden="true"
            />
            <CircleX
                v-else-if="check.state === 'failed'"
                class="size-5 shrink-0 text-severity-high"
                aria-hidden="true"
            />
            <Minus
                v-else
                class="size-5 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <span class="flex-1">{{ check.label }}</span>
            <span
                class="text-xs"
                :class="
                    check.state === 'failed'
                        ? 'font-medium text-severity-high'
                        : check.state === 'passed'
                          ? 'font-medium text-success'
                          : 'text-muted-foreground'
                "
                >{{ stateText[check.state] ?? check.state }}</span
            >
        </li>
    </ul>
</template>
