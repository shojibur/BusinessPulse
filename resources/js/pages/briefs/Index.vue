<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, FileText } from '@lucide/vue';
import { ref } from 'vue';
import AiUnavailableNotice from '@/components/AiUnavailableNotice.vue';
import BriefSourceBadge from '@/components/BriefSourceBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { show, store } from '@/routes/briefs';
import type { AiAvailability, BriefRow, Pagination } from '@/types';

defineProps<{
    briefs: BriefRow[];
    pagination: Pagination;
    ai: AiAvailability;
}>();

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

    <Card v-if="briefs.length === 0">
        <EmptyState
            :icon="FileText"
            message="No briefs yet. Generate one to summarise the current issues."
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
                            <span class="block text-xs text-muted-foreground">{{
                                formatDateTime(
                                    brief.generated_at ?? brief.created_at,
                                )
                            }}</span>
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
