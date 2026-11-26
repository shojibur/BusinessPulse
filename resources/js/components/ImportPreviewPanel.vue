<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Info } from '@lucide/vue';
import MetricTile from '@/components/MetricTile.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import WeekStateList from '@/components/WeekStateList.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { formatDate, formatDateRange, formatNumber } from '@/lib/format';
import { show } from '@/routes/imports';
import type { ImportPreview } from '@/types';

defineProps<{ preview: ImportPreview }>();
</script>

<template>
    <div class="space-y-6">
        <Alert v-if="preview.refreshed" role="status">
            <Info aria-hidden="true" />
            <AlertDescription>
                The preview was refreshed because sales data changed since this
                file was checked. The counts below are current.
            </AlertDescription>
        </Alert>

        <Alert v-if="preview.already_imported.length > 0" role="status">
            <Info aria-hidden="true" />
            <AlertDescription>
                <span class="font-medium">File already imported.</span>
                The same file content was imported before:
                <template
                    v-for="(other, index) in preview.already_imported"
                    :key="other.id"
                >
                    <Link
                        :href="show(other.id).url"
                        class="font-medium text-primary-text underline"
                    >
                        #{{ other.id }}
                    </Link>
                    <template v-if="index < preview.already_imported.length - 1"
                        >,
                    </template>
                </template>
                . Importing it again changes nothing.
            </AlertDescription>
        </Alert>

        <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            <MetricTile
                label="Total rows"
                :value="formatNumber(preview.counts.total)"
            />
            <MetricTile label="New" :value="formatNumber(preview.counts.new)" />
            <MetricTile
                label="Corrected"
                :value="formatNumber(preview.counts.corrected)"
            />
            <MetricTile
                label="Unchanged"
                :value="formatNumber(preview.counts.unchanged)"
            />
            <MetricTile
                label="Invalid"
                :value="formatNumber(preview.counts.invalid)"
            />
            <MetricTile
                label="Valid"
                :value="formatNumber(preview.counts.valid)"
            />
        </div>

        <p
            v-if="preview.counts.valid === 0"
            class="rounded-xl border border-l-4 border-l-severity-high bg-card p-4 text-sm font-medium"
            role="status"
        >
            Nothing can be imported: no row passed validation.
        </p>

        <dl
            v-else
            class="grid gap-4 rounded-xl border bg-card p-5 text-sm sm:grid-cols-2"
        >
            <div>
                <dt class="text-xs text-muted-foreground">
                    Date range of valid rows
                </dt>
                <dd class="font-medium">
                    {{ formatDate(preview.min_date) }} to
                    {{ formatDate(preview.max_date) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">
                    Branches in the file
                </dt>
                <dd class="font-medium">{{ preview.branches.join(', ') }}</dd>
            </div>
        </dl>

        <p
            v-if="preview.non_trading_day_rows > 0"
            class="text-sm text-muted-foreground"
        >
            {{ preview.non_trading_day_rows }} valid row(s) fall on a
            non-trading day. They are stored but ignored when checking whether a
            week is complete.
        </p>

        <section v-if="preview.weeks.length > 0" class="space-y-3">
            <SectionHeader
                title="Reporting weeks in this file"
                description="Incomplete weeks stay incomplete. Missing data is never treated as zero."
                :icon="CalendarDays"
            />
            <WeekStateList :weeks="preview.weeks" />

            <p class="text-sm" role="status">
                <template
                    v-if="(preview.uncovered.overdue_branch_days ?? 0) > 0"
                >
                    After this file, sales for
                    {{
                        formatDateRange(
                            preview.uncovered.first ?? '',
                            preview.uncovered.last ?? '',
                        )
                    }}
                    are still not received:
                    {{ preview.uncovered.overdue_branch_days }} branch-days
                    overdue (last due date
                    {{ formatDate(preview.uncovered.last_due_date) }}).
                </template>
                <template v-else>
                    No due branch-day remains uncovered after this file (last
                    due date {{ formatDate(preview.uncovered.last_due_date) }}).
                </template>
            </p>
        </section>
    </div>
</template>
