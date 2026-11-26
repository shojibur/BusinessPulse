<script setup lang="ts">
import { MessageSquare } from '@lucide/vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { Card } from '@/components/ui/card';
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

const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
</script>

<template>
    <Card class="gap-4 p-5" aria-labelledby="comments-heading">
        <SectionHeader
            id="comments-heading"
            title="Comments"
            :icon="MessageSquare"
        />
        <p v-if="comments.length === 0" class="text-sm text-muted-foreground">
            No comments yet.
        </p>
        <ul v-else class="space-y-4">
            <li
                v-for="comment in comments"
                :key="comment.id"
                class="flex gap-3"
            >
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary-text"
                    aria-hidden="true"
                >
                    {{ initials(comment.author.name) }}
                </span>
                <div class="min-w-0 flex-1 rounded-lg bg-muted/50 p-3">
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
                </div>
            </li>
        </ul>
        <form
            class="space-y-2 border-t pt-4"
            novalidate
            @submit.prevent="submit"
        >
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
    </Card>
</template>
