<script setup lang="ts">
import { ListChecks } from '@lucide/vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Link } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import DraftCard from '@/components/meetings/DraftCard.vue';
import type {
    DraftReview,
    MeetingDetail,
    StatusTone,
    TaskDraftSummary,
} from '@/types';

// S07: with `review` (Owner and Manager) each pending draft is reviewed in a DraftCard; without it the S06 read-only rows remain.
const props = defineProps<{
    drafts: TaskDraftSummary[];
    counts: MeetingDetail['draftCounts'];
    review: DraftReview | null;
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
        <SectionHeader
            id="drafts-heading"
            title="Proposed tasks"
            :description="`Proposals only: nobody is notified until a person approves a draft. ${summary}.`"
            :icon="ListChecks"
        >
            <StatusBadge label="AI generated, review before use" tone="info" />
        </SectionHeader>

        <template v-if="review">
            <Alert v-if="review.transcriptChangedSinceExtraction" role="status">
                <Info aria-hidden="true" />
                <AlertDescription>
                    The transcript was edited after these tasks were extracted.
                    Drafts whose quote is no longer in the transcript cannot be
                    approved.
                </AlertDescription>
            </Alert>

            <Card v-if="review.pending.length === 0">
                <EmptyState
                    :message="
                        review.emptyReason === 'approved'
                            ? 'All proposed tasks have been reviewed. Approved tasks are listed below.'
                            : review.emptyReason === 'rejected'
                              ? 'All proposed tasks were rejected.'
                              : 'No tasks were proposed yet. They appear after the transcript has been analysed.'
                    "
                />
            </Card>
            <ul v-else class="space-y-4">
                <li v-for="draft in review.pending" :key="draft.id">
                    <DraftCard :draft="draft" :review="review" />
                </li>
            </ul>

            <section
                v-if="review.history.length"
                class="space-y-3"
                aria-labelledby="history-heading"
            >
                <SectionHeader id="history-heading" title="Reviewed" />
                <ul class="space-y-2">
                    <li v-for="draft in review.history" :key="draft.id">
                        <Card class="gap-1 p-3 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusBadge
                                    :label="
                                        draft.status === 'approved'
                                            ? 'Approved'
                                            : 'Rejected'
                                    "
                                    :tone="
                                        draft.status === 'approved'
                                            ? 'success'
                                            : 'neutral'
                                    "
                                />
                                <span class="font-medium">{{
                                    draft.title
                                }}</span>
                            </div>
                            <p
                                v-if="draft.status === 'approved'"
                                class="text-muted-foreground"
                            >
                                Assigned to {{ draft.assigneeName }} · due
                                {{ formatDate(draft.dueDate) }}
                                <Link
                                    v-if="draft.taskUrl"
                                    :href="draft.taskUrl"
                                    class="ml-1 text-primary-text underline"
                                    >Open task</Link
                                >
                            </p>
                            <p
                                v-else-if="draft.rejectionReason"
                                class="text-muted-foreground"
                            >
                                Reason: {{ draft.rejectionReason }}
                            </p>
                        </Card>
                    </li>
                </ul>
            </section>
            <p
                v-if="review.supersededCount"
                class="text-xs text-muted-foreground"
            >
                {{ review.supersededCount }} earlier draft(s) were replaced by a
                newer extraction.
            </p>
        </template>

        <template v-else>
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
                                            draft.assignee.resolution ===
                                            'resolved'
                                        "
                                        class="font-medium"
                                        >{{ draft.assignee.name }}</span
                                    >
                                    <StatusBadge
                                        v-else
                                        :label="
                                            assigneeBadge[
                                                draft.assignee.resolution
                                            ].label
                                        "
                                        :tone="
                                            assigneeBadge[
                                                draft.assignee.resolution
                                            ].tone
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
                                            draft.deadline.resolution ===
                                            'resolved'
                                        "
                                        class="font-medium"
                                        >{{
                                            formatDate(draft.deadline.date)
                                        }}</span
                                    >
                                    <StatusBadge
                                        v-else
                                        :label="
                                            deadlineBadge[
                                                draft.deadline.resolution
                                            ].label
                                        "
                                        :tone="
                                            deadlineBadge[
                                                draft.deadline.resolution
                                            ].tone
                                        "
                                    />
                                    <span
                                        v-if="draft.deadline.suggestedText"
                                        class="text-muted-foreground"
                                        >“{{
                                            draft.deadline.suggestedText
                                        }}”</span
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
        </template>
    </section>
</template>
