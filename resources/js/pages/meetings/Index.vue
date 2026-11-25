<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Mic } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
            <Link :href="create().url">Create meeting</Link>
        </Button>
    </PageHeader>

    <div class="space-y-6">
        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1">
                <label for="status-filter" class="text-sm font-medium"
                    >Status</label
                >
                <NativeSelect
                    id="status-filter"
                    v-model="status"
                    class="min-w-48"
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
                <label for="issue-filter" class="text-sm font-medium"
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

        <Card v-if="meetings.length === 0">
            <EmptyState
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
        </Card>

        <template v-else>
            <Card class="hidden gap-0 p-0 md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Meeting</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Issues</TableHead>
                            <TableHead class="text-right"
                                >Pending drafts</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="meeting in meetings" :key="meeting.id">
                            <TableCell>
                                <Link
                                    :href="meeting.url"
                                    class="touch-target font-medium text-primary-text hover:underline"
                                    >{{ meeting.title }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{ meeting.department.name }}
                                </p>
                            </TableCell>
                            <TableCell>{{
                                formatDate(meeting.meetingDate)
                            }}</TableCell>
                            <TableCell
                                ><MeetingStatusBadge
                                    :status="meeting.status"
                                    :label="meeting.statusLabel"
                            /></TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                meeting.issueCount
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                meeting.pendingDraftCount
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>

            <ul class="space-y-3 md:hidden">
                <li v-for="meeting in meetings" :key="meeting.id">
                    <Card class="gap-2 p-4">
                        <Link
                            :href="meeting.url"
                            class="touch-target font-medium text-primary-text hover:underline"
                            >{{ meeting.title }}</Link
                        >
                        <div
                            class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                        >
                            <span>{{ formatDate(meeting.meetingDate) }}</span>
                            <MeetingStatusBadge
                                :status="meeting.status"
                                :label="meeting.statusLabel"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ meeting.issueCount }} issue(s) ·
                            {{ meeting.pendingDraftCount }} pending draft(s)
                        </p>
                    </Card>
                </li>
            </ul>

            <nav
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between"
                aria-label="Meeting pages"
            >
                <Button
                    variant="outline"
                    :disabled="!pagination.prev_url"
                    as-child
                >
                    <Link v-if="pagination.prev_url" :href="pagination.prev_url"
                        >Previous</Link
                    >
                    <span v-else>Previous</span>
                </Button>
                <span class="text-sm text-muted-foreground"
                    >Page {{ pagination.current_page }} of
                    {{ pagination.last_page }}</span
                >
                <Button
                    variant="outline"
                    :disabled="!pagination.next_url"
                    as-child
                >
                    <Link v-if="pagination.next_url" :href="pagination.next_url"
                        >Next</Link
                    >
                    <span v-else>Next</span>
                </Button>
            </nav>
        </template>
    </div>
</template>
