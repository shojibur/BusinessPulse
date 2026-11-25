<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { StaffRow } from '@/types';

defineProps<{ users: StaffRow[] }>();

const roleLabel = (role: StaffRow['role']): string =>
    role.charAt(0).toUpperCase() + role.slice(1);
</script>

<template>
    <Head title="Staff settings" />

    <Card class="gap-0 p-0">
        <EmptyState
            v-if="users.length === 0"
            :icon="Users"
            message="There are no users to show."
        />
        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Job title</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Department</TableHead>
                    <TableHead>Manager</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="user in users" :key="user.id">
                    <TableCell class="font-medium">{{ user.name }}</TableCell>
                    <TableCell>{{ user.email }}</TableCell>
                    <TableCell>{{ user.job_title ?? '—' }}</TableCell>
                    <TableCell>{{ roleLabel(user.role) }}</TableCell>
                    <TableCell>{{ user.department ?? '—' }}</TableCell>
                    <TableCell>{{ user.manager ?? '—' }}</TableCell>
                    <TableCell>
                        <StatusBadge
                            :label="user.is_active ? 'Active' : 'Inactive'"
                            :tone="user.is_active ? 'success' : 'neutral'"
                        />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </Card>
</template>
