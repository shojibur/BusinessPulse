<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, CircleCheck, TriangleAlert } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Card } from '@/components/ui/card';
import { index as issuesIndex } from '@/routes/issues';
import type { DashboardUrgentIssue } from './types';

defineProps<{ issues: DashboardUrgentIssue[] }>();

const dot: Record<string, string> = {
    critical: 'bg-severity-high',
    high: 'bg-severity-high',
    medium: 'bg-severity-medium',
    low: 'bg-slate-400',
};

const severityText: Record<string, string> = {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};
</script>

<template>
    <Card class="gap-0 p-0">
        <div class="border-b px-5 py-4">
            <SectionHeader
                title="Urgent business findings"
                description="Open issues, most severe first."
                :icon="TriangleAlert"
            >
                <Link
                    :href="issuesIndex().url"
                    class="text-sm font-medium text-primary-text hover:underline"
                    >View all</Link
                >
            </SectionHeader>
        </div>
        <EmptyState
            v-if="issues.length === 0"
            :icon="CircleCheck"
            message="No open issues. Everything the rules check looks fine."
        />
        <ul v-else class="divide-y">
            <li v-for="issue in issues" :key="issue.id">
                <Link
                    :href="issue.url"
                    class="flex items-center gap-3 px-5 py-3.5 transition-colors hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :class="dot[issue.severity] ?? 'bg-slate-400'"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-sm/5 font-medium text-foreground"
                            >{{ issue.title }}</span
                        >
                        <span
                            v-if="issue.detail"
                            class="block truncate text-xs/4 text-muted-foreground"
                            >{{ issue.detail }}</span
                        >
                    </span>
                    <span class="sr-only"
                        >{{ severityText[issue.severity] }} severity,
                        {{ issue.status_label }}</span
                    >
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>
    </Card>
</template>
