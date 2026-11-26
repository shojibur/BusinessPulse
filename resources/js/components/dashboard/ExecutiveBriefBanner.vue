<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { Card } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { index as briefsIndex, show as briefShow } from '@/routes/briefs';
import type { LatestBrief } from '@/types';

const props = defineProps<{
    brief: LatestBrief | null;
    /** Whether an AI provider is set up on this site. */
    aiConfigured: boolean;
}>();

// Say only what is true: AI wrote it, or it is a plain summary of the stored figures.
const subtitle = computed(() =>
    props.brief === null
        ? 'A short summary of your latest business data, issues and actions.'
        : props.brief.source === 'ai'
          ? 'Written by AI from your latest business data, issues and actions.'
          : 'A plain summary of your stored figures and open issues.',
);
</script>

<template>
    <Card
        class="gap-3 border-teal-200 bg-teal-50/60 p-5 shadow-none sm:p-6"
        aria-labelledby="exec-brief-heading"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-primary-text shadow-sm"
                >
                    <Sparkles class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2
                        id="exec-brief-heading"
                        class="text-base/6 font-semibold text-foreground"
                    >
                        Executive Brief
                    </h2>
                    <p class="text-xs/4 text-muted-foreground">
                        {{ subtitle }}
                    </p>
                </div>
            </div>
            <div
                v-if="brief"
                class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <span :title="formatDateTime(brief.generated_at)"
                    >Generated
                    {{ brief.ago ?? formatDateTime(brief.generated_at) }}</span
                >
                <Link
                    :href="briefShow(brief.id).url"
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary-text hover:underline"
                >
                    View full brief
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
                <Link
                    :href="briefsIndex().url"
                    class="text-sm font-medium text-muted-foreground hover:text-foreground hover:underline"
                >
                    Earlier briefs
                </Link>
            </div>
        </div>

        <div v-if="brief" class="border-s-4 border-primary ps-4">
            <p class="text-lg/7 font-semibold text-foreground">
                {{ brief.headline }}
            </p>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            No brief has been generated yet. Use Generate brief to create one.
            <template v-if="!aiConfigured">
                AI is not set up on this site, so it will be a plain summary of
                the stored figures.
            </template>
        </p>
    </Card>
</template>
