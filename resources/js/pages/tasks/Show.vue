<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
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

defineProps<{
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

    <div class="space-y-6">
        <StatusActions
            :task-id="task.id"
            :current="task.status"
            :transitions="can.changeStatus"
        />

        <Card class="gap-0 p-0">
            <dl class="grid divide-y text-sm sm:grid-cols-2 sm:divide-y-0">
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">Due date</dt>
                    <dd class="font-medium">{{ formatDate(task.dueDate) }}</dd>
                </div>
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">Assigned by</dt>
                    <dd class="font-medium">{{ task.assignedBy.name }}</dd>
                </div>
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">Created</dt>
                    <dd class="font-medium">
                        {{ formatDateTime(task.createdAt) }}
                    </dd>
                </div>
                <div class="px-6 py-3">
                    <dt class="text-muted-foreground">Completed</dt>
                    <dd class="font-medium">
                        {{ formatDateTime(task.completedAt) }}
                    </dd>
                </div>
                <div v-if="task.description" class="px-6 py-3 sm:col-span-2">
                    <dt class="text-muted-foreground">Description</dt>
                    <dd class="whitespace-pre-wrap">{{ task.description }}</dd>
                </div>
            </dl>
        </Card>

        <section
            v-if="meeting || excerpt || issue"
            class="space-y-3"
            aria-labelledby="context-heading"
        >
            <h2 id="context-heading" class="text-lg/7 font-semibold">
                Where this task came from
            </h2>
            <Card class="gap-3 p-4 text-sm">
                <p v-if="meeting">
                    Meeting:
                    <Link
                        v-if="meeting.url"
                        :href="meeting.url"
                        class="font-medium text-primary-text underline"
                        >{{ meeting.title }}</Link
                    >
                    <span v-else class="font-medium">{{ meeting.title }}</span>
                    · {{ formatDate(meeting.meetingDate) }}
                </p>
                <blockquote
                    v-if="excerpt"
                    class="border-l-2 pl-3 text-muted-foreground"
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
                        {{ issue.evidence.latest }} latest. This is an observed
                        change, not a cause.
                    </p>
                </template>
                <ul v-if="decisions?.length" class="list-disc space-y-1 pl-5">
                    <li v-for="(decision, i) in decisions" :key="i">
                        {{ decision.summary }}
                    </li>
                </ul>
            </Card>
        </section>

        <CommentThread :task-id="task.id" :comments="comments" />
    </div>
</template>
