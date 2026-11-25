<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
import { ref } from 'vue';
import BriefSourceBadge from '@/components/BriefSourceBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { show, store } from '@/routes/briefs';
import type { BriefRow, Pagination } from '@/types';

defineProps<{ briefs: BriefRow[]; pagination: Pagination }>();

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

    <div v-else class="space-y-3">
        <Card v-for="brief in briefs" :key="brief.id" class="p-4">
            <div class="flex flex-wrap items-center gap-2">
                <BriefSourceBadge
                    :status="brief.status"
                    :source="brief.source"
                />
                <span class="text-xs text-muted-foreground">{{
                    formatDateTime(brief.generated_at ?? brief.created_at)
                }}</span>
            </div>
            <Link
                :href="show(brief.id).url"
                class="touch-target mt-2 block font-medium text-primary-text hover:underline"
            >
                {{
                    brief.headline ??
                    'Brief' +
                        (brief.status === 'failed'
                            ? ' (failed)'
                            : ' is being prepared')
                }}
            </Link>
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
