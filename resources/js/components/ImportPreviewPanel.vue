<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import KpiCard from '@/components/KpiCard.vue';
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

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
            <KpiCard
                label="Total rows"
                :value="formatNumber(preview.counts.total)"
            />
            <KpiCard label="New" :value="formatNumber(preview.counts.new)" />
            <KpiCard
                label="Corrected"
                :value="formatNumber(preview.counts.corrected)"
            />
            <KpiCard
                label="Unchanged"
                :value="formatNumber(preview.counts.unchanged)"
            />
            <KpiCard
                label="Invalid"
                :value="formatNumber(preview.counts.invalid)"
            />
            <KpiCard
                label="Valid"
                :value="formatNumber(preview.counts.valid)"
            />
        </div>

        <p
            v-if="preview.counts.valid === 0"
            class="rounded-lg border bg-surface p-4 text-sm font-medium"
            role="status"
        >
            Nothing can be imported: no row passed validation.
        </p>

        <dl v-else class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Date range of valid rows</dt>
                <dd class="font-medium">
                    {{ formatDate(preview.min_date) }} to
                    {{ formatDate(preview.max_date) }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Branches in the file</dt>
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
            <h3 class="text-lg/7 font-semibold">
                Reporting weeks in this file
            </h3>
            <p class="text-sm text-muted-foreground">
                Incomplete weeks stay incomplete. Missing data is never treated
                as zero.
            </p>
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
