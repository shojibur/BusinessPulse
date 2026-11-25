<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { store } from '@/routes/tasks/comments';
import type { TaskCommentData } from '@/types';

const props = defineProps<{ taskId: number; comments: TaskCommentData[] }>();
const form = useForm({ body: '' });

function submit(): void {
    form.post(store(props.taskId).url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <section class="space-y-3" aria-labelledby="comments-heading">
        <h2 id="comments-heading" class="text-lg/7 font-semibold">Comments</h2>
        <p v-if="comments.length === 0" class="text-sm text-muted-foreground">
            No comments yet.
        </p>
        <ul v-else class="space-y-3">
            <li
                v-for="comment in comments"
                :key="comment.id"
                class="rounded-lg border p-3"
            >
                <p class="text-xs text-muted-foreground">
                    <span class="font-medium text-foreground">{{
                        comment.author.name
                    }}</span>
                    · {{ comment.author.role }} ·
                    {{ formatDateTime(comment.createdAt) }}
                </p>
                <!-- Plain text, escaped by Vue; line breaks kept by CSS. -->
                <p class="mt-1 text-sm whitespace-pre-wrap">
                    {{ comment.body }}
                </p>
            </li>
        </ul>
        <form class="space-y-2" novalidate @submit.prevent="submit">
            <Label for="comment-body">Add a comment</Label>
            <Textarea
                id="comment-body"
                v-model="form.body"
                rows="3"
                :aria-invalid="!!form.errors.body"
            />
            <p
                v-if="form.errors.body"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ form.errors.body }}
            </p>
            <Button
                type="submit"
                :disabled="form.processing || form.body.trim() === ''"
            >
                <Spinner v-if="form.processing" />
                Add comment
            </Button>
        </form>
    </section>
</template>
