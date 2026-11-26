<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { password as passwordRoute } from '@/routes/platform/companies/users';
import type { PlatformUserRow } from '@/types';

const props = defineProps<{
    companyId: number;
    user: PlatformUserRow | null;
}>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm({ password: '' });

function submit(): void {
    if (props.user === null) {
        return;
    }

    form.put(
        passwordRoute({ company: props.companyId, user: props.user.id }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            // Never keep the typed password.
            onFinish: () => form.reset('password'),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form novalidate class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Reset password</DialogTitle>
                    <DialogDescription>
                        Set a new password for {{ user?.name }}. Their open
                        sessions end immediately.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="reset-password"
                        >New password (at least 12 characters)</Label
                    >
                    <Input
                        id="reset-password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                    />
                    <p
                        v-if="form.errors.password"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Change password
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
