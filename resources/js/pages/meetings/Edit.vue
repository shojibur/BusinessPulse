<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MeetingForm from '@/components/meetings/MeetingForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { show, update } from '@/routes/meetings';
import type { IssueOption, MeetingFormMeeting } from '@/types';

const props = defineProps<{
    meeting: MeetingFormMeeting;
    departments: { id: number; name: string }[];
    issueOptions: IssueOption[];
    businessToday: string;
}>();
</script>

<template>
    <Head title="Edit meeting" />
    <PageHeader title="Edit meeting" />
    <MeetingForm
        :meeting="props.meeting"
        :departments="departments"
        :issue-options="issueOptions"
        :business-today="businessToday"
        :submit-url="update(props.meeting.id).url"
        :cancel-url="show(props.meeting.id).url"
    />
</template>
