<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleAlert, Quote } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import RejectDialog from '@/components/meetings/RejectDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { approve, reject, update } from '@/routes/task-drafts';
import type { DraftReview, DraftReviewItem } from '@/types';

const props = defineProps<{ draft: DraftReviewItem; review: DraftReview }>();

const only = ['meeting', 'taskDrafts', 'draftReview'];
const form = reactive({
    title: props.draft.title,
    description: props.draft.description,
    assignee_id:
        props.draft.assigneeId === null ? '' : String(props.draft.assigneeId),
    due_date: props.draft.dueDate ?? '',
    // A visible default only: the reviewer submits it explicitly.
    issue_id:
        props.draft.issueId !== null
            ? String(props.draft.issueId)
            : props.review.defaultIssueId !== null
              ? String(props.review.defaultIssueId)
              : '',
});
const errors = ref<Record<string, string>>({});
const busy = ref(false);
const rejecting = ref(false);

const payload = () => ({
    title: form.title,
    description: form.description,
    assignee_id: form.assignee_id === '' ? null : Number(form.assignee_id),
    due_date: form.due_date === '' ? null : form.due_date,
    issue_id: form.issue_id === '' ? null : Number(form.issue_id),
});

const blockedReason = computed(() => {
    if (props.draft.stale) {
        return 'The transcript changed and no longer supports this draft. Re-extract the tasks first.';
    }

    if (form.title.trim() === '') {
        return 'Enter a title.';
    }

    if (form.assignee_id === '') {
        return props.draft.assigneeResolution === 'ambiguous'
            ? 'Several employees match this name. Choose one.'
            : 'Choose an employee.';
    }

    if (form.due_date === '') {
        return 'Choose a deadline.';
    }

    return null;
});

function send(action: 'save' | 'approve'): void {
    busy.value = true;
    errors.value = {};
    const options = {
        preserveScroll: true,
        only,
        onError: (bag: Record<string, string>) => (errors.value = bag),
        onFinish: () => (busy.value = false),
    };

    if (action === 'approve') {
        router.post(approve(props.draft.id).url, payload(), options);
    } else {
        router.patch(update(props.draft.id).url, payload(), options);
    }
}

function doReject(reason: string): void {
    busy.value = true;
    router.post(
        reject(props.draft.id).url,
        { reason: reason.trim() === '' ? null : reason },
        {
            preserveScroll: true,
            only,
            onFinish: () => ((busy.value = false), (rejecting.value = false)),
        },
    );
}
</script>

<template>
    <Card class="gap-4 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <StatusBadge label="AI generated, review before use" tone="info" />
            <StatusBadge
                v-if="draft.assigneeResolution === 'ambiguous'"
                label="Ambiguous person"
                tone="warning"
            />
            <StatusBadge
                v-else-if="draft.assigneeResolution === 'unmatched'"
                label="Person not matched"
                tone="warning"
            />
            <StatusBadge
                v-else-if="draft.assigneeResolution === 'none'"
                label="No person named"
                tone="warning"
            />
            <StatusBadge
                v-if="draft.deadlineResolution !== 'resolved'"
                :label="
                    draft.deadlineResolution === 'ambiguous'
                        ? 'Unclear deadline'
                        : 'No deadline'
                "
                tone="warning"
            />
            <StatusBadge
                v-if="draft.stale"
                label="Stale evidence"
                tone="danger"
            />
        </div>

        <blockquote
            class="flex gap-2 rounded-md border-l-2 bg-muted/40 p-3 text-sm"
        >
            <Quote
                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <span
                ><span class="block text-xs font-medium text-muted-foreground"
                    >From the transcript</span
                >{{ draft.transcriptExcerpt }}</span
            >
        </blockquote>
        <p
            v-if="draft.suggestedAssigneeName || draft.suggestedDueDateText"
            class="text-xs text-muted-foreground"
        >
            The AI heard:
            <template v-if="draft.suggestedAssigneeName"
                >person “{{ draft.suggestedAssigneeName }}”</template
            >
            <template
                v-if="draft.suggestedAssigneeName && draft.suggestedDueDateText"
                >,
            </template>
            <template v-if="draft.suggestedDueDateText"
                >deadline “{{ draft.suggestedDueDateText }}”</template
            >.
        </p>

        <Alert v-if="draft.stale" variant="destructive" role="alert">
            <CircleAlert aria-hidden="true" />
            <AlertDescription
                >The transcript changed and no longer contains the quote above.
                This draft cannot be approved; re-extract the
                tasks.</AlertDescription
            >
        </Alert>

        <form class="grid gap-3" novalidate @submit.prevent="send('approve')">
            <div class="grid gap-1.5">
                <Label :for="`title-${draft.id}`">Task title</Label>
                <Input
                    :id="`title-${draft.id}`"
                    v-model="form.title"
                    maxlength="150"
                    :aria-invalid="!!errors.title"
                />
                <p
                    v-if="errors.title"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ errors.title }}
                </p>
            </div>
            <div class="grid gap-1.5">
                <Label :for="`desc-${draft.id}`">Description (optional)</Label>
                <Textarea
                    :id="`desc-${draft.id}`"
                    v-model="form.description"
                    rows="2"
                />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label :for="`assignee-${draft.id}`">Employee</Label>
                    <NativeSelect
                        :id="`assignee-${draft.id}`"
                        v-model="form.assignee_id"
                        :aria-invalid="!!errors.assignee_id"
                    >
                        <option value="">Choose an employee…</option>
                        <option
                            v-for="person in review.assigneeOptions"
                            :key="person.id"
                            :value="String(person.id)"
                        >
                            {{ person.name }}
                        </option>
                    </NativeSelect>
                    <p
                        v-if="
                            draft.assigneeResolution === 'ambiguous' &&
                            form.assignee_id === ''
                        "
                        class="text-xs text-muted-foreground"
                    >
                        Several employees match this name. Choose one.
                    </p>
                    <p
                        v-if="errors.assignee_id"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ errors.assignee_id }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`due-${draft.id}`">Deadline</Label>
                    <Input
                        :id="`due-${draft.id}`"
                        v-model="form.due_date"
                        type="date"
                        :min="review.minDueDate"
                        :aria-invalid="!!errors.due_date"
                    />
                    <p
                        v-if="errors.due_date"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ errors.due_date }}
                    </p>
                </div>
            </div>
            <div v-if="review.issueOptions.length" class="grid gap-1.5">
                <Label :for="`issue-${draft.id}`"
                    >Related issue (optional)</Label
                >
                <NativeSelect :id="`issue-${draft.id}`" v-model="form.issue_id">
                    <option value="">No related issue</option>
                    <option
                        v-for="issue in review.issueOptions"
                        :key="issue.id"
                        :value="String(issue.id)"
                    >
                        {{ issue.title }}
                    </option>
                </NativeSelect>
                <p
                    v-if="errors.issue_id"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ errors.issue_id }}
                </p>
            </div>
            <p
                v-if="errors.draft || errors.status"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ errors.draft ?? errors.status }}
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="submit"
                    :disabled="busy || blockedReason !== null"
                >
                    <Spinner v-if="busy" />
                    Approve and assign
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="busy"
                    @click="send('save')"
                    >Save draft</Button
                >
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="busy"
                    @click="rejecting = true"
                    >Reject</Button
                >
            </div>
            <p
                v-if="blockedReason"
                class="text-xs text-muted-foreground"
                role="status"
            >
                Approve is disabled: {{ blockedReason }}
            </p>
        </form>

        <RejectDialog
            v-model:open="rejecting"
            :busy="busy"
            @confirm="doReject"
        />
    </Card>
</template>
