<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarRange, ChevronRight, FileText } from '@lucide/vue';
import { computed, ref } from 'vue';
import AiUnavailableNotice from '@/components/AiUnavailableNotice.vue';
import BriefSourceBadge from '@/components/BriefSourceBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime } from '@/lib/format';
import { index, show, store } from '@/routes/briefs';
import type { AiAvailability, BriefRow, Pagination } from '@/types';

const props = defineProps<{
    briefs: BriefRow[];
    pagination: Pagination;
    ai: AiAvailability;
    filters: { from: string | null; to: string | null };
}>();

const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const filtered = computed(() => from.value !== '' || to.value !== '');

function applyDates(): void {
    router.get(
        index().url,
        { from: from.value || undefined, to: to.value || undefined },
        { preserveState: true, preserveScroll: true },
    );
}

function clearDates(): void {
    from.value = '';
    to.value = '';
    applyDates();
}

const generating = ref(false);
const error = ref<string | null>(null);

function generate(): void {
    generating.value = true;
    error.value = null;
    router.post(
        store().url,
        {},
        {
            onError: (bag) =>
                (error.value =
                    bag.brief ?? 'The brief could not be requested.'),
            onFinish: () => (generating.value = false),
        },
    );
}
</script>

<template>
    <Head title="Briefs" />

    <PageHeader
        title="Briefs"
        description="Executive summaries of the open issues, written from stored evidence."
    >
        <Button :disabled="generating" @click="generate">
            <Spinner v-if="generating" />
            <FileText v-else aria-hidden="true" />
            Generate brief
        </Button>
    </PageHeader>

    <AiUnavailableNotice v-if="!ai.text" class="mb-4" />

    <p v-if="error" class="mb-4 text-sm text-destructive" role="alert">
        {{ error }}
    </p>

    <!-- Find an earlier brief by the day its figures describe. -->
    <Card class="mb-4 gap-3 p-4">
        <form
            class="flex flex-wrap items-end gap-3"
            novalidate
            @submit.prevent="applyDates"
        >
            <CalendarRange
                class="mb-2 size-5 text-muted-foreground"
                aria-hidden="true"
            />
            <div class="grid gap-1">
                <Label for="brief-from" class="text-xs">From (as of)</Label>
                <Input id="brief-from" v-model="from" type="date" />
            </div>
            <div class="grid gap-1">
                <Label for="brief-to" class="text-xs">To (as of)</Label>
                <Input id="brief-to" v-model="to" type="date" />
            </div>
            <Button type="submit" variant="outline">Show briefs</Button>
            <Button
                v-if="filtered"
                type="button"
                variant="ghost"
                @click="clearDates"
                >Clear</Button
            >
        </form>
    </Card>

    <Card v-if="briefs.length === 0">
        <EmptyState
            :icon="FileText"
            :message="
                filtered
                    ? 'No briefs match these dates.'
                    : 'No briefs yet. Generate one to summarise the current issues.'
            "
        >
            <Button :disabled="generating" @click="generate"
                >Generate brief</Button
            >
        </EmptyState>
    </Card>

    <div v-else class="space-y-4">
        <Card class="gap-0 p-0">
            <ul class="divide-y">
                <li v-for="brief in briefs" :key="brief.id">
                    <Link
                        :href="show(brief.id).url"
                        class="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary-text"
                        >
                            <FileText class="size-5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">
                                {{
                                    brief.headline ??
                                    'Brief' +
                                        (brief.status === 'failed'
                                            ? ' (failed)'
                                            : ' is being prepared')
                                }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                <template v-if="brief.as_of_date"
                                    >As of {{ formatDate(brief.as_of_date) }} ·
                                </template>
                                <span
                                    :title="
                                        formatDateTime(
                                            brief.generated_at ??
                                                brief.created_at,
                                        )
                                    "
                                    >{{
                                        brief.ago ??
                                        formatDateTime(
                                            brief.generated_at ??
                                                brief.created_at,
                                        )
                                    }}</span
                                >
                            </span>
                        </span>
                        <BriefSourceBadge
                            :status="brief.status"
                            :source="brief.source"
                        />
                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </Card>

        <nav
            v-if="pagination.last_page > 1"
            class="flex items-center justify-between"
            aria-label="Pagination"
        >
            <Button variant="outline" :disabled="!pagination.prev_url" as-child>
                <Link v-if="pagination.prev_url" :href="pagination.prev_url"
                    >Previous</Link
                >
                <span v-else>Previous</span>
            </Button>
            <span class="text-sm text-muted-foreground"
                >Page {{ pagination.current_page }} of
                {{ pagination.last_page }}</span
            >
            <Button variant="outline" :disabled="!pagination.next_url" as-child>
                <Link v-if="pagination.next_url" :href="pagination.next_url"
                    >Next</Link
                >
                <span v-else>Next</span>
            </Button>
        </nav>
    </div>
</template>
