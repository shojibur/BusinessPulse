<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, Users } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import type { DepartmentDetail } from '@/types';

defineProps<{ department: DepartmentDetail | null }>();
</script>

<template>
    <Head title="Department settings" />

    <Card v-if="department === null">
        <EmptyState
            :icon="Building2"
            message="You are not assigned to a department yet."
        />
    </Card>

    <div
        v-else
        class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
    >
        <Card class="gap-0 p-0">
            <div class="border-b px-6 py-5">
                <SectionHeader
                    :title="department.name"
                    description="Department details."
                    :icon="Building2"
                />
            </div>
            <dl class="divide-y">
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm text-muted-foreground">
                        Expected branches
                    </dt>
                    <dd class="flex flex-wrap gap-2 sm:col-span-2">
                        <StatusBadge
                            v-for="branch in department.expected_branches"
                            :key="branch"
                            :label="branch"
                            tone="info"
                        />
                        <span
                            v-if="department.expected_branches.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            None set
                        </span>
                    </dd>
                </div>
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm text-muted-foreground">Manager</dt>
                    <dd class="text-sm font-medium sm:col-span-2">
                        {{
                            department.managers.map((m) => m.name).join(', ') ||
                            '—'
                        }}
                    </dd>
                </div>
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm text-muted-foreground">Active staff</dt>
                    <dd class="text-sm font-medium tabular-nums sm:col-span-2">
                        {{ department.active_staff_count }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Card class="gap-4 p-5">
            <SectionHeader title="Staff" :icon="Users" />
            <ul v-if="department.staff.length > 0" class="space-y-3 text-sm">
                <li
                    v-for="member in department.staff"
                    :key="member.id"
                    class="flex items-center gap-3"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary-text"
                        aria-hidden="true"
                    >
                        {{ member.name.charAt(0).toUpperCase() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{
                            member.name
                        }}</span>
                        <span class="block text-xs text-muted-foreground">
                            {{ member.job_title ?? '—' }}
                        </span>
                    </span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No active staff in this department.
            </p>
        </Card>
    </div>
</template>
