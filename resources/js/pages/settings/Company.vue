<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import { computed } from 'vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import type { CompanySettings } from '@/types';

const props = defineProps<{ company: CompanySettings }>();

const WEEKDAYS = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];

const tradingDays = computed(() =>
    props.company.trading_days.map((day) => WEEKDAYS[day - 1]).join(', '),
);

const rows = computed(() => {
    const items: { label: string; value: string }[] = [
        { label: 'Company name', value: props.company.name },
        { label: 'Timezone', value: props.company.timezone },
        { label: 'Currency', value: props.company.currency },
        { label: 'Trading days', value: tradingDays.value },
        {
            label: 'Reporting cutoff time',
            value: props.company.reporting_cutoff_time,
        },
        {
            label: 'Reporting cutoff lag',
            value: `${props.company.reporting_cutoff_lag_days} day(s)`,
        },
    ];

    if (props.company.recording_retention_days !== null) {
        items.push({
            label: 'Recording retention',
            value: `${props.company.recording_retention_days} days`,
        });
    }

    return items;
});
</script>

<template>
    <Head title="Company settings" />

    <Card class="gap-0 p-0">
        <div class="border-b px-6 py-5">
            <SectionHeader
                title="Company"
                description="How your business is set up in BusinessPulse."
                :icon="Building2"
            >
                <StatusBadge
                    v-if="company.is_demo"
                    label="Demo company"
                    tone="info"
                />
            </SectionHeader>
        </div>
        <dl class="divide-y">
            <div
                v-for="row in rows"
                :key="row.label"
                class="grid gap-1 px-6 py-4 sm:grid-cols-3 sm:gap-4"
            >
                <dt class="text-sm text-muted-foreground">{{ row.label }}</dt>
                <dd class="text-sm font-medium sm:col-span-2">
                    {{ row.value }}
                </dd>
            </div>
        </dl>
        <p class="border-t bg-muted/30 px-6 py-3 text-xs text-muted-foreground">
            These settings are read-only in this version.
        </p>
    </Card>
</template>
