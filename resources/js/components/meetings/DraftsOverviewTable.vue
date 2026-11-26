<script setup lang="ts">
import { CalendarDays, ListChecks } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import type { StatusTone, TaskDraftSummary } from '@/types';

defineProps<{ drafts: TaskDraftSummary[] }>();

const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

function badge(draft: TaskDraftSummary): { label: string; tone: StatusTone } {
    if (draft.status === 'approved') {
        return { label: 'Approved', tone: 'success' };
    }

    if (draft.status === 'rejected') {
        return { label: 'Rejected', tone: 'neutral' };
    }

    if (draft.assignee.resolution !== 'resolved') {
        return { label: 'Needs assignment', tone: 'warning' };
    }

    return draft.deadline.resolution !== 'resolved'
        ? { label: 'Needs deadline', tone: 'warning' }
        : { label: 'Pending review', tone: 'info' };
}
</script>

<template>
    <Card class="gap-0 p-0">
        <div class="border-b p-5">
            <SectionHeader
                title="Task drafts from this meeting"
                description="Proposals only. Nobody is notified until a person approves a draft."
                :icon="ListChecks"
            >
                <StatusBadge :label="`${drafts.length} items`" tone="neutral" />
            </SectionHeader>
        </div>
        <EmptyState
            v-if="drafts.length === 0"
            :icon="ListChecks"
            message="No task drafts yet. They appear after the decisions are extracted."
        />
        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-12">#</TableHead>
                    <TableHead>Task</TableHead>
                    <TableHead>Assignee</TableHead>
                    <TableHead>Deadline</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(draft, index) in drafts" :key="draft.id">
                    <TableCell class="text-muted-foreground tabular-nums">{{
                        index + 1
                    }}</TableCell>
                    <TableCell class="max-w-xs whitespace-normal">
                        <p class="font-medium text-foreground">
                            {{ draft.title }}
                        </p>
                        <p class="line-clamp-2 text-xs text-muted-foreground">
                            {{ draft.description }}
                        </p>
                    </TableCell>
                    <TableCell>
                        <div class="flex items-center gap-2">
                            <Avatar class="size-7">
                                <AvatarFallback
                                    class="bg-primary/10 text-[11px] font-semibold text-primary-text"
                                >
                                    {{
                                        draft.assignee.name
                                            ? initials(draft.assignee.name)
                                            : '?'
                                    }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="text-sm">
                                <template v-if="draft.assignee.name">{{
                                    draft.assignee.name
                                }}</template>
                                <template
                                    v-else-if="draft.assignee.suggestedName"
                                    >{{ draft.assignee.suggestedName }}
                                    <span class="text-xs text-muted-foreground"
                                        >({{
                                            draft.assignee.resolution ===
                                            'ambiguous'
                                                ? 'ambiguous'
                                                : 'not matched'
                                        }})</span
                                    ></template
                                >
                                <span v-else class="text-muted-foreground"
                                    >Unassigned</span
                                >
                            </span>
                        </div>
                    </TableCell>
                    <TableCell>
                        <span
                            v-if="draft.deadline.date"
                            class="inline-flex items-center gap-1.5 text-sm"
                        >
                            <CalendarDays
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            {{ formatDate(draft.deadline.date) }}
                        </span>
                        <span
                            v-else-if="draft.deadline.suggestedText"
                            class="text-sm text-muted-foreground"
                            >{{ draft.deadline.suggestedText }} (unclear)</span
                        >
                        <span v-else class="text-muted-foreground">—</span>
                    </TableCell>
                    <TableCell>
                        <StatusBadge
                            :label="badge(draft).label"
                            :tone="badge(draft).tone"
                        />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </Card>
</template>
