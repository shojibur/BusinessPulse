<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { status as statusRoute } from '@/routes/tasks';
import type { TaskStatusValue } from '@/types';

const props = defineProps<{
    taskId: number;
    current: TaskStatusValue;
    transitions: TaskStatusValue[];
}>();

const busy = ref(false);
const error = ref<string | null>(null);
const confirmCancel = ref(false);

const labels: Record<TaskStatusValue, string> = {
    pending: 'Pending',
    in_progress: 'Start working',
    blocked: 'Mark blocked',
    completed: 'Mark completed',
    cancelled: 'Cancel task',
};

function change(to: TaskStatusValue): void {
    busy.value = true;
    error.value = null;
    confirmCancel.value = false;
    router.post(
        statusRoute(props.taskId).url,
        { status: to },
        {
            preserveScroll: true,
            onError: (bag) =>
                (error.value =
                    bag.status ?? 'The status could not be changed.'),
            onFinish: () => (busy.value = false),
        },
    );
}

function label(to: TaskStatusValue): string {
    if (to === 'in_progress' && props.current === 'completed') {
        return 'Reopen';
    }

    if (to === 'in_progress' && props.current === 'blocked') {
        return 'Unblock';
    }

    return labels[to];
}
</script>

<template>
    <div v-if="transitions.length" class="space-y-2">
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="to in transitions"
                :key="to"
                :variant="
                    to === 'cancelled'
                        ? 'outline'
                        : to === 'completed'
                          ? 'default'
                          : 'secondary'
                "
                :disabled="busy"
                @click="
                    to === 'cancelled' ? (confirmCancel = true) : change(to)
                "
            >
                {{ label(to) }}
            </Button>
        </div>
        <p
            v-if="transitions.includes('blocked')"
            class="text-xs text-muted-foreground"
        >
            Marking a task blocked needs no explanation, but a comment helps
            your manager.
        </p>
        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>

        <Dialog v-model:open="confirmCancel">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel this task?</DialogTitle>
                    <DialogDescription
                        >A cancelled task cannot be
                        restarted.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="confirmCancel = false"
                        >Keep task</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="busy"
                        @click="change('cancelled')"
                        >Cancel task</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
