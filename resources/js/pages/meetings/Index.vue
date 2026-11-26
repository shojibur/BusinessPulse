<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Mic, Plus } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDate } from '@/lib/format';
import { create, index } from '@/routes/meetings';
import type { MeetingListItem, MeetingStatusValue, Pagination } from '@/types';

const props = defineProps<{
    meetings: MeetingListItem[];
    pagination: Pagination;
    filters: { status: MeetingStatusValue | null; issue: number | null };
    statusOptions: { value: string; label: string }[];
    issueOptions: { id: number; title: string }[];
    can: { create: boolean };
}>();

const status = ref(props.filters.status ?? '');
const issue = ref(
    props.filters.issue === null ? '' : String(props.filters.issue),
);

function applyFilters(): void {
    router.get(
        index().url,
        {
            ...(status.value ? { status: status.value } : {}),
            ...(issue.value ? { issue: issue.value } : {}),
        },
        { preserveState: true, replace: true },
    );
}

const filtered = (): boolean =>
    props.filters.status !== null || props.filters.issue !== null;
</script>

<template>
    <Head title="Meetings" />

    <PageHeader
        title="Meetings"
        description="Record what was decided, then review the tasks proposed from the discussion."
    >
        <Button v-if="can.create" as-child>
            <Link :href="create().url">
                <Plus aria-hidden="true" />
                Create meeting
            </Link>
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <Card class="gap-0 p-0">
            <div
                class="flex flex-wrap items-end justify-between gap-3 border-b px-5 py-4"
            >
                <SectionHeader
                    title="All meetings"
                    :description="`${pagination.total} in total`"
                    :icon="Mic"
                />
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1">
                        <label
                            for="status-filter"
                            class="text-xs font-medium text-muted-foreground"
                            >Status</label
                        >
                        <NativeSelect
                            id="status-filter"
                            v-model="status"
                            class="min-w-44"
                            @update:model-value="applyFilters"
                        >
                            <option value="">All statuses</option>
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </div>
                    <div class="grid gap-1">
                        <label
                            for="issue-filter"
                            class="text-xs font-medium text-muted-foreground"
                            >Issue</label
                        >
                        <NativeSelect
                            id="issue-filter"
                            v-model="issue"
                            class="min-w-56"
                            @update:model-value="applyFilters"
                        >
                            <option value="">All issues</option>
                            <option
                                v-for="option in issueOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ option.title }}
                            </option>
                        </NativeSelect>
                    </div>
                </div>
            </div>

            <EmptyState
                v-if="meetings.length === 0"
                :icon="Mic"
                :message="
                    filtered()
                        ? 'No meetings match the selected filters.'
                        : 'No meetings yet. Create one to record decisions and turn them into proposed tasks.'
                "
            >
                <Button v-if="can.create" as-child
                    ><Link :href="create().url">Create meeting</Link></Button
                >
            </EmptyState>

            <ul v-else class="divide-y">
                <li v-for="meeting in meetings" :key="meeting.id">
                    <Link
                        :href="meeting.url"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 transition-colors outline-none hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary-text"
                        >
                            <Mic class="size-5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1 basis-56">
                            <span
                                class="block text-sm font-medium text-foreground"
                                >{{ meeting.title }}</span
                            >
                            <span class="block text-xs text-muted-foreground">
                                {{ meeting.department.name }} ·
                                {{ formatDate(meeting.meetingDate) }}
                            </span>
                        </span>
                        <span
                            class="flex items-center gap-5 text-sm tabular-nums"
                        >
                            <span class="text-right">
                                <span class="block font-semibold">{{
                                    meeting.issueCount
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >issues</span
                                >
                            </span>
                            <span class="text-right">
                                <span
                                    class="block font-semibold"
                                    :class="
                                        meeting.pendingDraftCount > 0
                                            ? 'text-severity-medium'
                                            : ''
                                    "
                                    >{{ meeting.pendingDraftCount }}</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >pending drafts</span
                                >
                            </span>
                        </span>
                        <MeetingStatusBadge
                            :status="meeting.status"
                            :label="meeting.statusLabel"
                        />
                        <ChevronRight
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </Card>

        <nav
            v-if="meetings.length > 0 && pagination.last_page > 1"
            class="flex items-center justify-between"
            aria-label="Meeting pages"
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
