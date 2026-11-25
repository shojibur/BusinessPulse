<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
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

    <Card v-else class="gap-0 p-0">
        <div class="border-b px-6 py-4">
            <h2 class="text-lg/7 font-semibold">{{ department.name }}</h2>
        </div>
        <dl class="divide-y">
            <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-muted-foreground">Expected branches</dt>
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
            <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-muted-foreground">Manager</dt>
                <dd class="text-sm font-medium sm:col-span-2">
                    {{
                        department.managers.map((m) => m.name).join(', ') || '—'
                    }}
                </dd>
            </div>
            <div class="grid gap-1 px-6 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-muted-foreground">Active staff</dt>
                <dd class="text-sm font-medium tabular-nums sm:col-span-2">
                    {{ department.active_staff_count }}
                </dd>
            </div>
        </dl>
        <div class="border-t px-6 py-4">
            <h3 class="mb-2 text-sm font-semibold">Staff</h3>
            <ul v-if="department.staff.length > 0" class="divide-y text-sm">
                <li
                    v-for="member in department.staff"
                    :key="member.id"
                    class="flex flex-wrap justify-between gap-2 py-2"
                >
                    <span class="font-medium">{{ member.name }}</span>
                    <span class="text-muted-foreground">
                        {{ member.job_title ?? '—' }}
                    </span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No active staff in this department.
            </p>
        </div>
    </Card>
</template>
