<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    Clock,
    Mic,
    Plus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import {
    groupLabels,
    groupOf,
    groupOrder,
} from '@/components/meetings/meetingGroups';
import type { MeetingGroup } from '@/components/meetings/meetingGroups';
import MeetingBriefStrip from '@/components/meetings/MeetingBriefStrip.vue';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/format';
import { create, index } from '@/routes/meetings';
import type {
    LatestBriefSummary,
    MeetingListItem,
    MeetingStatusValue,
    Pagination,
} from '@/types';

const props = defineProps<{
    meetings: MeetingListItem[];
    pagination: Pagination;
    filters: { status: MeetingStatusValue | null; issue: number | null };
    statusOptions: { value: string; label: string }[];
    issueOptions: { id: number; title: string }[];
    can: { create: boolean };
    latestBrief: LatestBriefSummary | null;
    businessToday: string;
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

// The tabs group the meetings on this page by where they are in their life.
const grouped = computed(() => {
    const result: Record<MeetingGroup, MeetingListItem[]> = {
        upcoming: [],
        in_progress: [],
        completed: [],
    };

    for (const meeting of props.meetings) {
        result[groupOf(meeting, props.businessToday)].push(meeting);
    }

    return result;
});

const tab = ref<string>(
    groupOrder.find((g) => grouped.value[g].length > 0) ?? 'in_progress',
);

const statusIcon = (value: MeetingStatusValue) =>
    value === 'ready_for_review'
        ? CircleCheck
        : value === 'failed'
          ? CircleAlert
          : value === 'draft'
            ? CircleDashed
            : Clock;

const statusColor = (value: MeetingStatusValue): string =>
    value === 'ready_for_review'
        ? 'text-success'
        : value === 'failed'
          ? 'text-severity-high'
          : 'text-muted-foreground';
</script>

<template>
    <Head title="Meetings" />

    <PageHeader
        title="Meetings and decisions"
        description="Turn business discussions into clear decisions and executable actions."
    >
        <Button v-if="can.create" as-child>
            <Link :href="create().url">
                <Plus aria-hidden="true" />
                New meeting
            </Link>
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <MeetingBriefStrip v-if="latestBrief" :brief="latestBrief" />

        <Card class="gap-0 p-0">
            <div class="space-y-4 border-b p-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <SectionHeader
                        title="Management meetings"
                        :description="`${pagination.total} in total`"
                        :icon="CalendarDays"
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

                <Tabs v-model="tab">
                    <TabsList class="max-w-full overflow-x-auto">
                        <TabsTrigger
                            v-for="group in groupOrder"
                            :key="group"
                            :value="group"
                        >
                            {{ groupLabels[group] }}
                            ({{ grouped[group].length }})
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent
                        v-for="group in groupOrder"
                        :key="group"
                        :value="group"
                        class="mt-0"
                    />
                </Tabs>
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
                    ><Link :href="create().url">New meeting</Link></Button
                >
            </EmptyState>
            <p
                v-else-if="grouped[tab as MeetingGroup].length === 0"
                class="px-5 py-8 text-center text-sm text-muted-foreground"
            >
                No {{ groupLabels[tab as MeetingGroup].toLowerCase() }}
                meetings on this page.
            </p>

            <ul v-else class="divide-y">
                <li
                    v-for="meeting in grouped[tab as MeetingGroup]"
                    :key="meeting.id"
                >
                    <Link
                        :href="meeting.url"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 transition-colors outline-none hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset"
                    >
                        <component
                            :is="statusIcon(meeting.status)"
                            class="size-5 shrink-0"
                            :class="statusColor(meeting.status)"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1 basis-56">
                            <span
                                class="block text-sm font-medium text-foreground"
                                >{{ meeting.title }}</span
                            >
                            <span class="block text-xs text-muted-foreground">
                                {{ formatDate(meeting.meetingDate) }} ·
                                {{ meeting.department.name }}
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
