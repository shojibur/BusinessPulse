<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { formatDateTime } from '@/lib/format';
import {
    index,
    ownerPassword,
    reactivate,
    suspend,
} from '@/routes/platform/companies';
import type {
    PlatformActivityRow,
    PlatformCompanyDetail,
    PlatformOwner,
} from '@/types';

const props = defineProps<{
    company: PlatformCompanyDetail;
    owner: PlatformOwner | null;
    activity: PlatformActivityRow[];
}>();

const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const actionLabels: Record<string, string> = {
    'company.created': 'Company created',
    'company.suspended': 'Company suspended',
    'company.reactivated': 'Company reactivated',
    'company.owner_password_reset': 'Owner password reset',
};

const confirming = ref(false);
const busy = ref(false);

function toggleSuspension(): void {
    busy.value = true;
    const route = props.company.suspended
        ? reactivate(props.company.id)
        : suspend(props.company.id);
    router.post(
        route.url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
                confirming.value = false;
            },
        },
    );
}

const passwordForm = useForm({ password: '' });

function resetPassword(): void {
    passwordForm.put(ownerPassword(props.company.id).url, {
        preserveScroll: true,
        onFinish: () => passwordForm.reset('password'),
    });
}
</script>

<template>
    <Head :title="company.name" />

    <PageHeader :title="company.name">
        <StatusBadge
            :label="company.suspended ? 'Suspended' : 'Active'"
            :tone="company.suspended ? 'warning' : 'success'"
        />
        <Button variant="outline" as-child
            ><Link :href="index().url">All companies</Link></Button
        >
    </PageHeader>

    <div class="grid gap-6 md:grid-cols-2">
        <Card class="gap-3 p-5">
            <h2 class="text-lg/7 font-semibold">Company</h2>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-muted-foreground">Time zone</dt>
                <dd>{{ company.timezone }}</dd>
                <dt class="text-muted-foreground">Trading days</dt>
                <dd>
                    {{
                        company.trading_days
                            .map((d) => dayNames[d - 1])
                            .join(', ')
                    }}
                </dd>
                <dt class="text-muted-foreground">Branches</dt>
                <dd>
                    {{
                        company.branches.length
                            ? company.branches.join(', ')
                            : 'None set'
                    }}
                </dd>
                <dt class="text-muted-foreground">Users</dt>
                <dd>{{ company.users_count }}</dd>
                <dt class="text-muted-foreground">Departments</dt>
                <dd>{{ company.departments_count }}</dd>
                <dt class="text-muted-foreground">Created</dt>
                <dd>{{ formatDateTime(company.created_at) }}</dd>
                <template v-if="company.suspended_at">
                    <dt class="text-muted-foreground">Suspended</dt>
                    <dd>{{ formatDateTime(company.suspended_at) }}</dd>
                </template>
            </dl>
            <p class="text-xs text-muted-foreground">
                Business data of this company is not shown here by design.
            </p>
        </Card>

        <Card class="gap-3 p-5">
            <h2 class="text-lg/7 font-semibold">Owner</h2>
            <template v-if="owner">
                <p class="text-sm">
                    {{ owner.name }} · {{ owner.email }}
                    <StatusBadge
                        v-if="!owner.is_active"
                        class="ml-1"
                        label="Inactive"
                        tone="neutral"
                    />
                </p>
                <form
                    class="grid gap-2"
                    novalidate
                    @submit.prevent="resetPassword"
                >
                    <Label for="new-password"
                        >New Owner password (at least 12 characters)</Label
                    >
                    <Input
                        id="new-password"
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                    />
                    <p
                        v-if="passwordForm.errors.password"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ passwordForm.errors.password }}
                    </p>
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="passwordForm.processing"
                        >
                            <Spinner v-if="passwordForm.processing" />
                            Reset password
                        </Button>
                    </div>
                </form>
            </template>
            <p v-else class="text-sm text-muted-foreground">
                This company has no Owner.
            </p>
        </Card>
    </div>

    <Card class="mt-6 gap-3 p-5">
        <h2 class="text-lg/7 font-semibold">Access</h2>
        <p class="text-sm text-muted-foreground">
            {{
                company.suspended
                    ? 'This company is suspended. Nobody from it can sign in.'
                    : 'Suspending a company stops every one of its users from signing in. Nothing is deleted.'
            }}
        </p>
        <div>
            <Button
                :variant="company.suspended ? 'default' : 'destructive'"
                size="sm"
                @click="confirming = true"
            >
                {{
                    company.suspended ? 'Reactivate company' : 'Suspend company'
                }}
            </Button>
        </div>
    </Card>

    <Card class="mt-6 gap-3 p-5">
        <h2 class="text-lg/7 font-semibold">Platform activity</h2>
        <p v-if="activity.length === 0" class="text-sm text-muted-foreground">
            No platform actions yet.
        </p>
        <ul v-else class="divide-y text-sm">
            <li
                v-for="entry in activity"
                :key="entry.id"
                class="flex flex-wrap justify-between gap-2 py-2"
            >
                <span>{{ actionLabels[entry.action] ?? entry.action }}</span>
                <span class="text-muted-foreground">
                    {{ entry.by ?? 'System' }} · {{ formatDateTime(entry.at) }}
                </span>
            </li>
        </ul>
    </Card>

    <Dialog v-model:open="confirming">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ company.suspended ? 'Reactivate' : 'Suspend' }}
                    {{ company.name }}?
                </DialogTitle>
                <DialogDescription>
                    {{
                        company.suspended
                            ? 'Its users will be able to sign in again.'
                            : 'Its users are signed out on their next request and cannot sign in until you reactivate it.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="confirming = false"
                    >Cancel</Button
                >
                <Button
                    :variant="company.suspended ? 'default' : 'destructive'"
                    :disabled="busy"
                    @click="toggleSuspension"
                >
                    {{ company.suspended ? 'Reactivate' : 'Suspend' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
