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
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/meetings/recording';

const props = defineProps<{ meetingId: number }>();
const open = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);

function confirmDelete(): void {
    busy.value = true;
    error.value = null;
    router.delete(destroy(props.meetingId).url, {
        preserveScroll: true,
        onError: (errors) => {
            error.value = errors.recording ?? 'The recording was not deleted.';
        },
        onSuccess: () => {
            open.value = false;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">Delete recording</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete this recording?</DialogTitle>
                <DialogDescription>
                    The audio file is removed and cannot be restored. The
                    transcript, decisions and task drafts are kept.
                </DialogDescription>
            </DialogHeader>
            <p v-if="error" class="text-sm text-destructive" role="alert">
                {{ error }}
            </p>
            <DialogFooter>
                <Button variant="outline" @click="open = false"
                    >Keep recording</Button
                >
                <Button
                    variant="destructive"
                    :disabled="busy"
                    @click="confirmDelete"
                    >Delete recording</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
