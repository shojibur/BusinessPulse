<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { IssueOption, MeetingFormMeeting } from '@/types';

const props = defineProps<{
    meeting: MeetingFormMeeting | null;
    departments: { id: number; name: string }[];
    issueOptions: IssueOption[];
    businessToday: string;
    preselectedIssueId?: number | null;
    submitUrl: string;
    cancelUrl: string;
}>();

const preselected = props.preselectedIssueId ?? null;
const preselectedDepartment = props.issueOptions.find(
    (i) => i.id === preselected,
)?.departmentId;

const form = useForm({
    title: props.meeting?.title ?? '',
    meeting_date: props.meeting?.meetingDate ?? props.businessToday,
    department_id:
        props.meeting?.departmentId ??
        preselectedDepartment ??
        props.departments[0]?.id ??
        null,
    issue_ids:
        props.meeting?.issueIds ?? (preselected !== null ? [preselected] : []),
});

const editing = props.meeting !== null;
const singleDepartment = computed(() => props.departments.length <= 1);
const availableIssues = computed(() =>
    props.issueOptions.filter(
        (i) =>
            i.departmentId === undefined ||
            i.departmentId === form.department_id,
    ),
);
const futureDate = computed(() => form.meeting_date > props.businessToday);
const dateChangedWithDrafts = computed(
    () =>
        editing &&
        props.meeting!.hasDrafts &&
        form.meeting_date !== props.meeting!.meetingDate,
);

watch(
    () => form.department_id,
    () => {
        const allowed = new Set(availableIssues.value.map((i) => i.id));
        form.issue_ids = form.issue_ids.filter((id) => allowed.has(id));
    },
);

function submit(): void {
    if (editing) {
        form.put(props.submitUrl);
    } else {
        form.post(props.submitUrl);
    }
}
</script>

<template>
    <form class="max-w-2xl space-y-5" novalidate @submit.prevent="submit">
        <div class="grid gap-1.5">
            <Label for="title">Title</Label>
            <Input
                id="title"
                v-model="form.title"
                maxlength="150"
                required
                :aria-invalid="!!form.errors.title"
            />
            <p
                v-if="form.errors.title"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ form.errors.title }}
            </p>
        </div>

        <div class="grid gap-1.5">
            <Label for="meeting_date">Meeting date</Label>
            <Input
                id="meeting_date"
                v-model="form.meeting_date"
                type="date"
                required
                :disabled="meeting?.dateLocked"
                :aria-invalid="!!form.errors.meeting_date"
            />
            <p
                v-if="form.errors.meeting_date"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ form.errors.meeting_date }}
            </p>
            <Alert v-if="futureDate" role="status"
                ><AlertDescription
                    >This date is in the future.</AlertDescription
                ></Alert
            >
            <Alert v-if="dateChangedWithDrafts" role="status">
                <AlertDescription
                    >Changing the date does not change existing task draft
                    deadlines. Re-extract recomputes them from the new
                    date.</AlertDescription
                >
            </Alert>
        </div>

        <div v-if="!singleDepartment && !editing" class="grid gap-1.5">
            <Label for="department_id">Department</Label>
            <NativeSelect id="department_id" v-model="form.department_id">
                <option
                    v-for="department in departments"
                    :key="department.id"
                    :value="department.id"
                >
                    {{ department.name }}
                </option>
            </NativeSelect>
            <p
                v-if="form.errors.department_id"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ form.errors.department_id }}
            </p>
        </div>

        <fieldset class="grid gap-2">
            <legend class="text-sm font-medium">
                Linked issues (up to 10)
            </legend>
            <p
                v-if="availableIssues.length === 0"
                class="text-sm text-muted-foreground"
            >
                There are no issues in this department to link.
            </p>
            <label
                v-for="issue in availableIssues"
                :key="issue.id"
                class="flex items-start gap-2 text-sm"
            >
                <input
                    v-model="form.issue_ids"
                    type="checkbox"
                    :value="issue.id"
                    class="mt-1 size-4"
                />
                <span>{{ issue.title }}</span>
            </label>
            <p
                v-if="form.errors.issue_ids || form.errors['issue_ids.0']"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ form.errors.issue_ids ?? form.errors['issue_ids.0'] }}
            </p>
        </fieldset>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ editing ? 'Save changes' : 'Create meeting' }}
            </Button>
            <Button variant="outline" as-child
                ><Link :href="cancelUrl">Cancel</Link></Button
            >
        </div>
    </form>
</template>
