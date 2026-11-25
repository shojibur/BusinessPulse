<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MeetingForm from '@/components/meetings/MeetingForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { index, store } from '@/routes/meetings';
import type { IssueOption, MeetingFormMeeting } from '@/types';

defineProps<{
    meeting: MeetingFormMeeting | null;
    departments: { id: number; name: string }[];
    issueOptions: IssueOption[];
    businessToday: string;
    preselectedIssueId: number | null;
}>();
</script>

<template>
    <Head title="Create meeting" />
    <PageHeader
        title="Create meeting"
        description="Record the meeting first, then add a recording or paste the transcript."
    />
    <MeetingForm
        :meeting="meeting"
        :departments="departments"
        :issue-options="issueOptions"
        :business-today="businessToday"
        :preselected-issue-id="preselectedIssueId"
        :submit-url="store().url"
        :cancel-url="index().url"
    />
</template>
