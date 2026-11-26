<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/platform/companies';

const props = defineProps<{
    timezones: string[];
    defaults: { timezone: string; trading_days: number[] };
}>();

const days = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

const form = useForm({
    name: '',
    timezone: props.defaults.timezone,
    trading_days: [...props.defaults.trading_days],
    branches: '',
    owner_name: '',
    owner_email: '',
    owner_password: '',
});

function submit(): void {
    form.post(store().url, {
        // Never keep or redisplay the Owner password.
        onFinish: () => form.reset('owner_password'),
    });
}
</script>

<template>
    <Head title="New company" />

    <PageHeader
        title="New company"
        description="Sets up the company, its Sales department with default rules, and the first Owner."
    />

    <form class="max-w-2xl space-y-6" novalidate @submit.prevent="submit">
        <Card class="gap-4 p-5">
            <h2 class="text-lg/7 font-semibold">Company</h2>
            <div class="grid gap-2">
                <Label for="name">Company name</Label>
                <Input id="name" v-model="form.name" v-focus required />
                <p
                    v-if="form.errors.name"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.name }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="timezone">Time zone</Label>
                <NativeSelect id="timezone" v-model="form.timezone">
                    <option v-for="tz in timezones" :key="tz" :value="tz">
                        {{ tz }}
                    </option>
                </NativeSelect>
                <p
                    v-if="form.errors.timezone"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.timezone }}
                </p>
            </div>
            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">Trading days</legend>
                <div class="flex flex-wrap gap-3">
                    <label
                        v-for="day in days"
                        :key="day.value"
                        class="flex items-center gap-1.5 text-sm"
                    >
                        <input
                            v-model="form.trading_days"
                            type="checkbox"
                            :value="day.value"
                            class="size-4"
                        />
                        {{ day.label }}
                    </label>
                </div>
                <p
                    v-if="form.errors.trading_days"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.trading_days }}
                </p>
            </fieldset>
            <div class="grid gap-2">
                <Label for="branches">Branch codes (optional)</Label>
                <Input
                    id="branches"
                    v-model="form.branches"
                    placeholder="KL, PG, JB"
                />
                <p class="text-xs text-muted-foreground">
                    The branches that must report sales every trading day.
                    Separate with commas. The Owner can change them later.
                </p>
                <p
                    v-if="form.errors.branches"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.branches }}
                </p>
            </div>
        </Card>

        <Card class="gap-4 p-5">
            <h2 class="text-lg/7 font-semibold">First Owner</h2>
            <div class="grid gap-2">
                <Label for="owner_name">Full name</Label>
                <Input id="owner_name" v-model="form.owner_name" required />
                <p
                    v-if="form.errors.owner_name"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.owner_name }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="owner_email">Email address</Label>
                <Input
                    id="owner_email"
                    v-model="form.owner_email"
                    type="email"
                    autocomplete="off"
                    required
                />
                <p
                    v-if="form.errors.owner_email"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.owner_email }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="owner_password"
                    >Password (at least 12 characters)</Label
                >
                <Input
                    id="owner_password"
                    v-model="form.owner_password"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <p
                    v-if="form.errors.owner_password"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.owner_password }}
                </p>
            </div>
        </Card>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Create company
            </Button>
            <Button variant="outline" as-child
                ><Link :href="index().url">Cancel</Link></Button
            >
        </div>
    </form>
</template>
