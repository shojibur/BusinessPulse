<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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

const props = defineProps<{ companies: PlatformCompanyRow[] }>();

const query = ref('');

const visible = computed(() => {
    const term = query.value.trim().toLowerCase();

    return term === ''
        ? props.companies
        : props.companies.filter(
              (company) =>
                  company.name.toLowerCase().includes(term) ||
                  (company.owner_email ?? '').toLowerCase().includes(term),
          );
});
</script>

<template>
    <Head title="Companies" />

    <PageHeader
        title="Companies"
        description="Every business that uses BusinessPulse. You manage the company, never its business data."
    >
        <Button as-child>
            <Link :href="create().url">
                <Plus aria-hidden="true" />
                New company
            </Link>
        </Button>
    </PageHeader>

    <Card class="gap-0 p-0">
        <div class="border-b p-4">
            <div class="relative max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="query"
                    type="search"
                    class="pl-9"
                    placeholder="Search by company or Owner email"
                    aria-label="Search companies"
                />
            </div>
        </div>

        <EmptyState
            v-if="companies.length === 0"
            :icon="Building2"
            message="No company has been set up yet."
        />
        <EmptyState
            v-else-if="visible.length === 0"
            :icon="Search"
            message="No company matches your search."
        />
        <Table v-else>
            <TableHeader>
                <TableRow>
                    <TableHead>Company</TableHead>
                    <TableHead>Owner</TableHead>
                    <TableHead class="text-right">Users</TableHead>
                    <TableHead>Time zone</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="company in visible" :key="company.id">
                    <TableCell>
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary-text"
                                aria-hidden="true"
                            >
                                {{ company.name.charAt(0).toUpperCase() }}
                            </span>
                            <Link
                                :href="show(company.id).url"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ company.name }}</Link
                            >
                            <StatusBadge
                                v-if="company.is_demo"
                                label="Demo"
                                tone="info"
                            />
                        </div>
                    </TableCell>
                    <TableCell>{{ company.owner_email ?? '—' }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{
                        company.users_count
                    }}</TableCell>
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
