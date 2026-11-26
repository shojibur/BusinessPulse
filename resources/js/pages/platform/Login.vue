<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/platform/login';

const form = useForm({ email: '', password: '' });

function submit(): void {
    form.post(store().url, {
        // Never keep or redisplay the submitted password, whatever the outcome.
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Platform sign in" />

    <form class="flex flex-col gap-6" novalidate @submit.prevent="submit">
        <p class="text-center text-sm font-medium">Platform admin sign in</p>
        <div class="grid gap-4">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    v-focus
                    type="email"
                    name="email"
                    autocomplete="username"
                    required
                    :aria-invalid="form.errors.email ? true : undefined"
                    aria-describedby="login-errors"
                />
            </div>
            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    :aria-invalid="form.errors.email ? true : undefined"
                    aria-describedby="login-errors"
                />
            </div>
            <div id="login-errors" role="alert" aria-live="assertive">
                <p
                    v-if="form.errors.email || form.errors.password"
                    class="text-sm font-medium text-severity-high"
                >
                    {{ form.errors.email ?? form.errors.password }}
                </p>
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Sign in
            </Button>
        </div>
    </form>
</template>
