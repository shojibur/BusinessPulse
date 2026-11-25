<script setup lang="ts">
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import type { MeetingDetail, StatusTone, TaskDraftSummary } from '@/types';

// Read-only in S06: no approve, reject or edit control. S07 extends this component with the review controls.
const props = defineProps<{
    drafts: TaskDraftSummary[];
    counts: MeetingDetail['draftCounts'];
}>();

const assigneeBadge: Record<string, { label: string; tone: StatusTone }> = {
    ambiguous: { label: 'Ambiguous person', tone: 'warning' },
    unmatched: { label: 'Person not matched', tone: 'warning' },
    none: { label: 'No assignee', tone: 'neutral' },
};

const deadlineBadge: Record<string, { label: string; tone: StatusTone }> = {
    ambiguous: { label: 'Unclear deadline', tone: 'warning' },
    none: { label: 'No deadline', tone: 'neutral' },
};

const summary = computed(
    () =>
        `${props.counts.pending} pending, ${props.counts.approved} approved, ${props.counts.rejected} rejected, ${props.counts.superseded} superseded`,
);
</script>

<template>
    <section class="space-y-4" aria-labelledby="drafts-heading">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="drafts-heading" class="text-lg/7 font-semibold">
                Proposed tasks
            </h2>
            <StatusBadge label="AI generated, review before use" tone="info" />
        </div>
        <p class="text-sm text-muted-foreground">
            These are proposals, not assigned tasks. Nobody is notified and
            nothing is assigned until a person approves a draft. {{ summary }}.
        </p>

        <Card v-if="drafts.length === 0">
            <EmptyState
                message="No task drafts yet. They appear here after the transcript has been analysed."
            />
        </Card>

        <ul v-else class="space-y-3">
            <li v-for="draft in drafts" :key="draft.id">
                <Card class="gap-3 p-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <h3 class="font-medium">{{ draft.title }}</h3>
                        <StatusBadge
                            :label="draft.status"
                            :tone="
                                draft.status === 'pending'
                                    ? 'info'
                                    : draft.status === 'approved'
                                      ? 'success'
                                      : 'neutral'
                            "
                        />
                    </div>
                    <p v-if="draft.description" class="text-sm">
                        {{ draft.description }}
                    </p>
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground">
                                Suggested person
                            </dt>
                            <dd class="flex flex-wrap items-center gap-2">
                                <span
                                    v-if="
                                        draft.assignee.resolution === 'resolved'
                                    "
                                    class="font-medium"
                                    >{{ draft.assignee.name }}</span
                                >
                                <StatusBadge
                                    v-else
                                    :label="
                                        assigneeBadge[draft.assignee.resolution]
                                            .label
                                    "
                                    :tone="
                                        assigneeBadge[draft.assignee.resolution]
                                            .tone
                                    "
                                />
                                <span
                                    v-if="
                                        draft.assignee.resolution !==
                                            'resolved' &&
                                        draft.assignee.suggestedName
                                    "
                                    class="text-muted-foreground"
                                >
                                    heard as “{{
                                        draft.assignee.suggestedName
                                    }}”
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Suggested deadline
                            </dt>
                            <dd class="flex flex-wrap items-center gap-2">
                                <span
                                    v-if="
                                        draft.deadline.resolution === 'resolved'
                                    "
                                    class="font-medium"
                                    >{{ formatDate(draft.deadline.date) }}</span
                                >
                                <StatusBadge
                                    v-else
                                    :label="
                                        deadlineBadge[draft.deadline.resolution]
                                            .label
                                    "
                                    :tone="
                                        deadlineBadge[draft.deadline.resolution]
                                            .tone
                                    "
                                />
                                <span
                                    v-if="draft.deadline.suggestedText"
                                    class="text-muted-foreground"
                                    >“{{ draft.deadline.suggestedText }}”</span
                                >
                            </dd>
                        </div>
                    </dl>
                    <blockquote
                        class="border-l-2 pl-3 text-sm text-muted-foreground"
                    >
                        {{ draft.excerpt }}
                    </blockquote>
                </Card>
            </li>
        </ul>
    </section>
</template>
