<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CalendarDays, CircleAlert, CircleCheck } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { today } from '@/routes';
import type { DailySales } from '@/components/dashboard/types';

const props = defineProps<{
    daily: DailySales;
    /** The past reporting week being viewed, kept when the day changes. */
    viewingWeek: string | null;
}>();

const isLatest = computed(() => props.daily.date === props.daily.default_date);

function chooseDay(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    const query: Record<string, string> = {};

    if (props.viewingWeek) {
        query.week = props.viewingWeek;
    }

    if (value !== '' && value !== props.daily.default_date) {
        query.day = value;
    }

    router.get(today().url, query, {
        preserveScroll: true,
        preserveState: true,
        only: ['data'],
    });
}
</script>

<template>
    <Card class="gap-4 p-5">
        <SectionHeader
            title="Daily sales"
            :description="`${formatDate(daily.date)}${isLatest ? ' (latest sales day)' : ''}. Informational: rules and briefs stay weekly.`"
            :icon="CalendarDays"
        >
            <div class="grid gap-1">
                <label for="daily-date" class="sr-only">Choose a day</label>
                <Input
                    id="daily-date"
                    type="date"
                    :value="daily.date"
                    :min="daily.min_date"
                    :max="daily.max_date"
                    @change="chooseDay"
                />
            </div>
        </SectionHeader>

        <EmptyState
            v-if="daily.total === null"
            :icon="CalendarDays"
            :message="daily.message ?? 'No sales were recorded for this day.'"
        />

        <template v-else>
            <p
                v-if="daily.message"
                class="flex items-center gap-2 rounded-md bg-severity-medium-bg px-3 py-2 text-xs text-severity-medium"
                role="status"
            >
                <CircleAlert class="size-4 shrink-0" aria-hidden="true" />
                {{ daily.message }}
            </p>
            <div class="overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Branch</TableHead>
                            <TableHead class="text-right">Revenue</TableHead>
                            <TableHead class="text-right">Target</TableHead>
                            <TableHead class="text-right">Of target</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="branch in daily.branches"
                            :key="branch.code"
                        >
                            <TableCell class="font-medium">{{
                                branch.code
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                branch.revenue ?? '—'
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                branch.target ?? '—'
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                branch.achievement ?? '—'
                            }}</TableCell>
                            <TableCell>
                                <StatusBadge
                                    :label="
                                        branch.reported ? 'Reported' : 'Missing'
                                    "
                                    :tone="
                                        branch.reported ? 'success' : 'warning'
                                    "
                                />
                            </TableCell>
                        </TableRow>
                        <TableRow class="bg-muted/30 font-semibold">
                            <TableCell>
                                <span class="inline-flex items-center gap-1.5">
                                    <CircleCheck
                                        class="size-4 text-primary-text"
                                        aria-hidden="true"
                                    />
                                    Total
                                </span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                daily.total.revenue
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                daily.total.target
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                daily.total.achievement ?? '—'
                            }}</TableCell>
                            <TableCell
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ daily.total.reported_count }} of
                                {{ daily.total.expected_count }} branches
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </template>
    </Card>
</template>
