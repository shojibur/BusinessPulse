<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Ellipsis,
    KeyRound,
    Pencil,
    Plus,
    UserCheck,
    UserX,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/platform/ConfirmDialog.vue';
import CompanyUserDialog from '@/components/platform/CompanyUserDialog.vue';
import ResetPasswordDialog from '@/components/platform/ResetPasswordDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { index, reactivate, suspend } from '@/routes/platform/companies';
import {
    deactivate,
    reactivate as reactivateUser,
} from '@/routes/platform/companies/users';
import type {
    PlatformActivityRow,
    PlatformCompanyDetail,
    PlatformDepartmentOption,
    PlatformManagerOption,
    PlatformUserRow,
} from '@/types';

const props = defineProps<{
    company: PlatformCompanyDetail;
    users: PlatformUserRow[];
    departments: PlatformDepartmentOption[];
    managers: PlatformManagerOption[];
    activity: PlatformActivityRow[];
}>();

const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const actionLabels: Record<string, string> = {
    'company.created': 'Company created',
    'company.suspended': 'Company suspended',
    'company.reactivated': 'Company reactivated',
    'user.created': 'User added',
    'user.updated': 'User updated',
    'user.deactivated': 'User deactivated',
    'user.reactivated': 'User reactivated',
    'user.password_reset': 'User password reset',
};
const roleLabels: Record<string, string> = {
    owner: 'Owner',
    manager: 'Manager',
    staff: 'Staff',
};

// Company suspension.
const confirmingSuspension = ref(false);
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
                confirmingSuspension.value = false;
            },
        },
    );
}

// Users.
const userDialogOpen = ref(false);
const editing = ref<PlatformUserRow | null>(null);
const passwordUser = ref<PlatformUserRow | null>(null);
const passwordOpen = computed({
    get: () => passwordUser.value !== null,
    set: (value: boolean) => {
        if (!value) {
            passwordUser.value = null;
        }
    },
});
const deactivating = ref<PlatformUserRow | null>(null);
const deactivateOpen = computed({
    get: () => deactivating.value !== null,
    set: (value: boolean) => {
        if (!value) {
            deactivating.value = null;
        }
    },
});

function addUser(): void {
    editing.value = null;
    userDialogOpen.value = true;
}

function editUser(user: PlatformUserRow): void {
    editing.value = user;
    userDialogOpen.value = true;
}

function confirmDeactivate(): void {
    if (deactivating.value === null) {
        return;
    }

    busy.value = true;
    router.post(
        deactivate({
            company: props.company.id,
            user: deactivating.value.id,
        }).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
                deactivating.value = null;
            },
        },
    );
}

function activate(user: PlatformUserRow): void {
    router.post(
        reactivateUser({ company: props.company.id, user: user.id }).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="company.name" />

    <PageHeader :title="company.name">
        <StatusBadge
            :label="company.suspended ? 'Suspended' : 'Active'"
            :tone="company.suspended ? 'warning' : 'success'"
        />
        <Button variant="outline" as-child>
            <Link :href="index().url">All companies</Link>
        </Button>
        <Button
            :variant="company.suspended ? 'default' : 'destructive'"
            @click="confirmingSuspension = true"
        >
            {{ company.suspended ? 'Reactivate company' : 'Suspend company' }}
        </Button>
    </PageHeader>

    <Card class="mb-6 p-5">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <dt class="text-xs text-muted-foreground">Time zone</dt>
                <dd class="mt-0.5 font-medium">{{ company.timezone }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Trading days</dt>
                <dd class="mt-0.5 font-medium">
                    {{
                        company.trading_days
                            .map((d) => dayNames[d - 1])
                            .join(', ')
                    }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Branches</dt>
                <dd class="mt-0.5 font-medium">
                    {{
                        company.branches.length
                            ? company.branches.join(', ')
                            : 'None set'
                    }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Created</dt>
                <dd class="mt-0.5 font-medium">
                    {{ formatDateTime(company.created_at) }}
                </dd>
            </div>
            <div v-if="company.suspended_at">
                <dt class="text-xs text-muted-foreground">Suspended</dt>
                <dd class="mt-0.5 font-medium">
                    {{ formatDateTime(company.suspended_at) }}
                </dd>
            </div>
        </dl>
        <p class="mt-4 text-xs text-muted-foreground">
            Business data of this company is not shown here by design.
        </p>
    </Card>

    <Card class="gap-0 p-0">
        <div class="flex items-center justify-between border-b px-5 py-4">
            <div>
                <h2 class="text-base font-semibold">
                    Users ({{ users.length }})
                </h2>
                <p class="text-xs text-muted-foreground">
                    Add people, change roles, reset passwords and deactivate
                    accounts. Users are never deleted.
                </p>
            </div>
            <Button @click="addUser">
                <Plus aria-hidden="true" />
                Add user
            </Button>
        </div>
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Department</TableHead>
                    <TableHead>Reports to</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead class="w-12"
                        ><span class="sr-only">Actions</span></TableHead
                    >
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="user in users" :key="user.id">
                    <TableCell>
                        <p class="font-medium">{{ user.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ user.email
                            }}<template v-if="user.job_title">
                                · {{ user.job_title }}</template
                            >
                        </p>
                    </TableCell>
                    <TableCell>{{
                        roleLabels[user.role] ?? user.role
                    }}</TableCell>
                    <TableCell>{{ user.department ?? '—' }}</TableCell>
                    <TableCell>{{ user.manager ?? '—' }}</TableCell>
                    <TableCell>
                        <StatusBadge
                            :label="user.is_active ? 'Active' : 'Inactive'"
                            :tone="user.is_active ? 'success' : 'neutral'"
                        />
                    </TableCell>
                    <TableCell>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Actions for ${user.name}`"
                                >
                                    <Ellipsis aria-hidden="true" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-48">
                                <DropdownMenuItem @select="editUser(user)">
                                    <Pencil aria-hidden="true" />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem @select="passwordUser = user">
                                    <KeyRound aria-hidden="true" />
                                    Reset password
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-if="user.is_active"
                                    class="text-destructive"
                                    @select="deactivating = user"
                                >
                                    <UserX aria-hidden="true" />
                                    Deactivate
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-else
                                    @select="activate(user)"
                                >
                                    <UserCheck aria-hidden="true" />
                                    Reactivate
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </Card>

    <Card class="mt-6 gap-3 p-5">
        <SectionHeader title="Platform activity" />
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
                    {{ entry.by ?? 'System' }} ·
                    {{ formatDateTime(entry.at) }}
                </span>
            </li>
        </ul>
    </Card>

    <CompanyUserDialog
        v-model:open="userDialogOpen"
        :company-id="company.id"
        :departments="departments"
        :managers="managers"
        :user="editing"
    />
    <ResetPasswordDialog
        v-model:open="passwordOpen"
        :company-id="company.id"
        :user="passwordUser"
    />
    <ConfirmDialog
        v-model:open="deactivateOpen"
        :title="`Deactivate ${deactivating?.name ?? ''}?`"
        description="They are signed out now and cannot sign in until you reactivate them. Their work and history stay."
        confirm-label="Deactivate"
        destructive
        :busy="busy"
        @confirm="confirmDeactivate"
    />
    <ConfirmDialog
        v-model:open="confirmingSuspension"
        :title="`${company.suspended ? 'Reactivate' : 'Suspend'} ${company.name}?`"
        :description="
            company.suspended
                ? 'Its users will be able to sign in again.'
                : 'Its users are signed out on their next request and cannot sign in until you reactivate it.'
        "
        :confirm-label="company.suspended ? 'Reactivate' : 'Suspend'"
        :destructive="!company.suspended"
        :busy="busy"
        @confirm="toggleSuspension"
    />
</template>
