<script setup lang="ts">
defineProps<{ checks: { name: string; label: string; state: string }[] }>();

const stateText: Record<string, string> = {
    passed: 'Passed',
    failed: 'Failed',
    skipped: 'Not checked',
    not_applicable: 'Not applicable',
};
</script>

<template>
    <ul class="space-y-1 text-sm">
        <li
            v-for="check in checks"
            :key="check.name"
            class="flex flex-wrap justify-between gap-2"
        >
            <span>{{ check.label }}</span>
            <span
                :class="
                    check.state === 'failed'
                        ? 'font-medium text-severity-high'
                        : 'text-muted-foreground'
                "
                >{{ stateText[check.state] ?? check.state }}</span
            >
        </li>
    </ul>
</template>
