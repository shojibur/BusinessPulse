<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Info, MessageSquareQuote } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import StepTracker from '@/components/StepTracker.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import AssigneeAvatar from '@/components/tasks/AssigneeAvatar.vue';
import CommentThread from '@/components/tasks/CommentThread.vue';
import OverdueBadge from '@/components/tasks/OverdueBadge.vue';
import StatusActions from '@/components/tasks/StatusActions.vue';
import TaskStatusBadge from '@/components/tasks/TaskStatusBadge.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDate, formatDateTime } from '@/lib/format';
import { mine } from '@/routes/tasks';
import type {
    TaskCommentData,
    TaskDetailData,
    TaskIssueContext,
    TaskStatusValue,
} from '@/types';

const props = defineProps<{
    task: TaskDetailData;
    meeting: {
        id: number;
        title: string;
        meetingDate: string;
        url: string | null;
    } | null;
    excerpt: string | null;
    decisions: { summary: string; excerpt: string }[] | null;
    issue: TaskIssueContext | null;
    comments: TaskCommentData[];
    can: { changeStatus: TaskStatusValue[]; comment: boolean };
    businessToday: string;
}>();

// Progress of the work: pending, in progress, completed. Blocked stays on "in progress"; cancelled has no tracker.
const steps = [
    { key: 'pending', label: 'Pending' },
    { key: 'in_progress', label: 'In progress' },
    { key: 'completed', label: 'Completed' },
];

const currentStep = computed(() =>
    props.task.status === 'completed'
        ? steps.length
        : props.task.status === 'pending'
          ? 0
          : 1,
);
</script>

<template>
    <Head :title="task.title" />

    <PageHeader
        :title="task.title"
        :description="`${task.department.name} · assigned to ${task.assignee.name}`"
    >
        <OverdueBadge :days="task.daysOverdue" />
        <TaskStatusBadge :status="task.status" :label="task.statusLabel" />
        <Button variant="outline" as-child
            ><Link :href="mine().url">My tasks</Link></Button
        >
    </PageHeader>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <!-- Main column: the work and the conversation. -->
        <div class="min-w-0 space-y-6">
            <Card v-if="task.status !== 'cancelled'" class="gap-3 p-5">
                <StepTracker :steps="steps" :current="currentStep" />
                <p
                    v-if="task.status === 'blocked'"
                    class="text-center text-xs font-medium text-severity-medium"
                    role="status"
                >
                    This task is blocked.
                </p>
            </Card>

            <Card v-if="task.description" class="gap-2 p-5">
                <SectionHeader title="Description" />
                <p class="text-sm whitespace-pre-wrap">
                    {{ task.description }}
                </p>
            </Card>

            <section
                v-if="meeting || excerpt || issue"
                class="space-y-3"
                aria-labelledby="context-heading"
            >
                <SectionHeader
                    id="context-heading"
                    title="Where this task came from"
                    :icon="MessageSquareQuote"
                />
                <Card class="gap-3 p-5 text-sm">
                    <p v-if="meeting">
                        Meeting:
                        <Link
                            v-if="meeting.url"
                            :href="meeting.url"
                            class="font-medium text-primary-text underline"
                            >{{ meeting.title }}</Link
                        >
                        <span v-else class="font-medium">{{
                            meeting.title
                        }}</span>
                        · {{ formatDate(meeting.meetingDate) }}
                    </p>
                    <blockquote
                        v-if="excerpt"
                        class="border-l-4 border-primary/30 pl-3 text-muted-foreground italic"
                    >
                        {{ excerpt }}
                    </blockquote>
                    <template v-if="issue">
                        <p>
                            Related issue:
                            <Link
                                v-if="issue.url"
                                :href="issue.url"
                                class="font-medium text-primary-text underline"
                                >{{ issue.title }}</Link
                            >
                            <span v-else class="font-medium">{{
                                issue.title
                            }}</span>
                        </p>
                        <Alert v-if="issue.resolvedNotice" role="status">
                            <Info aria-hidden="true" />
                            <AlertDescription>{{
                                issue.resolvedNotice
                            }}</AlertDescription>
                        </Alert>
                        <p v-if="issue.evidence" class="text-muted-foreground">
                            {{ issue.evidence.metricName }}:
                            {{ issue.evidence.baseline }} when detected,
                            {{ issue.evidence.latest }} latest. This is an
                            observed change, not a cause.
                        </p>
                    </template>
                    <ul
                        v-if="decisions?.length"
                        class="list-disc space-y-1 pl-5"
                    >
                        <li v-for="(decision, i) in decisions" :key="i">
                            {{ decision.summary }}
                        </li>
                    </ul>
                </Card>
            </section>

            <CommentThread :task-id="task.id" :comments="comments" />
        </div>

        <!-- Side column: update the status and the key facts. -->
        <aside class="min-w-0 space-y-6">
            <Card v-if="can.changeStatus.length" class="gap-4 p-5">
                <SectionHeader title="Update status" />
                <StatusActions
                    :task-id="task.id"
                    :current="task.status"
                    :transitions="can.changeStatus"
                />
            </Card>

            <Card class="gap-4 p-5">
                <SectionHeader title="Details" />
                <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Due date</dt>
                        <dd class="font-medium">
                            {{ formatDate(task.dueDate) }}
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-muted-foreground">Assignee</dt>
                        <dd class="mt-1 flex items-center gap-2 font-medium">
                            <AssigneeAvatar :name="task.assignee.name" />
                            {{ task.assignee.name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Assigned by
                        </dt>
                        <dd class="font-medium">{{ task.assignedBy.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Created</dt>
                        <dd class="font-medium">
                            {{ formatDateTime(task.createdAt) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Completed</dt>
                        <dd class="font-medium">
                            {{ formatDateTime(task.completedAt) }}
                        </dd>
                    </div>
                </dl>
            </Card>
        </aside>
    </div>
</template>
