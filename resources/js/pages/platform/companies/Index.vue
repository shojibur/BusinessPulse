<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { create, show } from '@/routes/platform/companies';
import type { PlatformCompanyRow } from '@/types';

defineProps<{ companies: PlatformCompanyRow[] }>();
</script>

<template>
    <Head title="Companies" />

    <PageHeader
        title="Companies"
        description="Every business that uses BusinessPulse. You manage the company, never its business data."
    >
        <Button as-child><Link :href="create().url">New company</Link></Button>
    </PageHeader>

    <Card class="gap-0 p-0">
        <EmptyState
            v-if="companies.length === 0"
            :icon="Building2"
            message="No company has been set up yet."
        />
        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Company</TableHead>
                    <TableHead>Owner</TableHead>
                    <TableHead>Users</TableHead>
                    <TableHead>Time zone</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="company in companies" :key="company.id">
                    <TableCell class="font-medium">
                        <Link
                            :href="show(company.id).url"
                            class="underline-offset-4 hover:underline"
                            >{{ company.name }}</Link
                        >
                        <StatusBadge
                            v-if="company.is_demo"
                            class="ml-2"
                            label="Demo"
                            tone="info"
                        />
                    </TableCell>
                    <TableCell>{{ company.owner_email ?? '—' }}</TableCell>
                    <TableCell>{{ company.users_count }}</TableCell>
                    <TableCell>{{ company.timezone }}</TableCell>
                    <TableCell>{{ formatDate(company.created_at) }}</TableCell>
                    <TableCell>
                        <StatusBadge
                            :label="company.suspended ? 'Suspended' : 'Active'"
                            :tone="company.suspended ? 'warning' : 'success'"
                        />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </Card>
</template>
