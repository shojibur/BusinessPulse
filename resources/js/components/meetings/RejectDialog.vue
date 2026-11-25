<script setup lang="ts">
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{ busy: boolean }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ confirm: [reason: string] }>();
const reason = ref('');
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject this proposed task?</DialogTitle>
                <DialogDescription
                    >No task is created. The draft stays in the
                    history.</DialogDescription
                >
            </DialogHeader>
            <div class="grid gap-1.5">
                <Label for="reject-reason"
                    >Reason (optional, up to 500 characters)</Label
                >
                <Textarea
                    id="reject-reason"
                    v-model="reason"
                    rows="3"
                    maxlength="500"
                />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="open = false"
                    >Keep draft</Button
                >
                <Button
                    variant="destructive"
                    :disabled="busy"
                    @click="emit('confirm', reason)"
                    >Reject draft</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
