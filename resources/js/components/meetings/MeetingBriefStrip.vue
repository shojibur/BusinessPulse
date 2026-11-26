<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, FileText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import type { LatestBriefSummary } from '@/types';

defineProps<{ brief: LatestBriefSummary }>();
</script>

<template>
    <Card class="flex-row flex-wrap items-center gap-x-5 gap-y-3 p-5">
        <span
            class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-primary-text"
        >
            <FileText class="size-6" aria-hidden="true" />
        </span>
        <div class="min-w-0 flex-1 basis-72">
            <p
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Latest executive brief
            </p>
            <p class="truncate text-base/6 font-semibold text-foreground">
                {{ brief.headline }}
            </p>
            <p v-if="brief.generatedAt" class="text-xs text-muted-foreground">
                Generated {{ formatDateTime(brief.generatedAt) }}
            </p>
        </div>
        <Button variant="outline" as-child>
            <Link :href="brief.url">
                View brief
                <ArrowRight aria-hidden="true" />
            </Link>
        </Button>
    </Card>
</template>
