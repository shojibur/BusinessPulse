<script setup lang="ts">
import { CircleCheck, Clock, Database } from '@lucide/vue';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { formatDate, formatDateRange } from '@/lib/format';
import type { Freshness } from '@/types';

const props = defineProps<{ freshness: Freshness }>();

const view = computed(() => {
    const f = props.freshness;

    if (!f.configured) {
        return {
            icon: Database,
            title: 'Expected branches are not configured',
            text: 'Sales data cannot be imported until the expected branches are set for this department.',
        };
    }

    if (f.latest_sales_date === null) {
        return {
            icon: Database,
            title: 'No sales data yet',
            text: 'Upload a sales CSV to get started.',
        };
    }

    if (f.state === 'current') {
        return {
            icon: CircleCheck,
            title: `Sales received up to ${formatDate(f.last_due_date)}`,
            text: `Latest sales date: ${formatDate(f.latest_sales_date)}.`,
        };
    }

    return {
        icon: Clock,
        title: `Sales for ${formatDateRange(f.overdue_from ?? f.latest_sales_date, f.overdue_to ?? f.last_due_date)} not yet received: ${f.overdue_branch_days ?? 0} branch-days overdue`,
        text: `Latest sales date: ${formatDate(f.latest_sales_date)}. Last due date: ${formatDate(f.last_due_date)}.`,
    };
});
</script>

<template>
    <Alert role="status">
        <component :is="view.icon" aria-hidden="true" />
        <AlertTitle>{{ view.title }}</AlertTitle>
        <AlertDescription>{{ view.text }}</AlertDescription>
    </Alert>
</template>
