<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    CircleCheck,
    CirclePause,
    Plus,
    Sparkles,
    Users,
} from '@lucide/vue';
import StatCard from '@/components/platform/StatCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDate, formatDateTime } from '@/lib/format';
import { edit as aiEdit } from '@/routes/platform/ai';
import { create, index, show } from '@/routes/platform/companies';
import type {
    PlatformAiRow,
    PlatformDashboardActivity,
    PlatformRecentCompany,
    PlatformStats,
} from '@/types';

defineProps<{
    stats: PlatformStats;
    recent: PlatformRecentCompany[];
    ai: PlatformAiRow[];
    activity: PlatformDashboardActivity[];
}>();

const actionLabels: Record<string, string> = {
    'company.created': 'created a company',
    'company.suspended': 'suspended a company',
    'company.reactivated': 'reactivated a company',
    'company.owner_password_reset': 'reset an Owner password',
    'ai.settings_updated': 'updated the AI settings',
    'ai.connection_tested': 'tested the AI connection',
    'platform.signed_in': 'signed in',
};

const providerLabel = (provider: string): string =>
    provider === 'anthropic' ? 'Claude' : 'OpenAI-compatible';
</script>

<template>
    <Head title="Overview" />

    <PageHeader
        title="Overview"
        description="Every business on the platform, and whether the AI services are ready."
    >
        <Button variant="outline" as-child>
            <Link :href="aiEdit().url">
                <Sparkles aria-hidden="true" />
                AI settings
            </Link>
        </Button>
        <Button as-child>
            <Link :href="create().url">
                <Plus aria-hidden="true" />
                New company
            </Link>
        </Button>
    </PageHeader>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
            label="Companies"
            :value="stats.companies"
            :icon="Building2"
            :caption="stats.demo ? `${stats.demo} demo` : undefined"
        />
        <StatCard
            label="Active"
            :value="stats.active"
            :icon="CircleCheck"
            tone="success"
        />
        <StatCard
            label="Suspended"
            :value="stats.suspended"
            :icon="CirclePause"
            tone="warning"
        />
        <StatCard
            label="Users"
            :value="stats.users"
            :icon="Users"
            tone="info"
        />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <Card class="gap-0 p-0 xl:col-span-2">
            <div class="flex items-center justify-between border-b px-5 py-4">
                <h2 class="text-base font-semibold">Newest companies</h2>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="index().url">View all</Link>
                </Button>
            </div>
            <p
                v-if="recent.length === 0"
                class="px-5 py-8 text-center text-sm text-muted-foreground"
            >
                No company has been set up yet.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="company in recent"
                    :key="company.id"
                    class="flex items-center gap-3 px-5 py-3"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary-text"
                        aria-hidden="true"
                    >
                        {{ company.name.charAt(0).toUpperCase() }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="show(company.id).url"
                            class="block truncate text-sm font-medium hover:underline"
                        >
                            {{ company.name }}
                        </Link>
                        <p class="text-xs text-muted-foreground">
                            {{ company.users_count }} users · created
                            {{ formatDate(company.created_at) }}
                        </p>
                    </div>
                    <StatusBadge
                        v-if="company.is_demo"
                        label="Demo"
                        tone="info"
                    />
                    <StatusBadge
                        :label="company.suspended ? 'Suspended' : 'Active'"
                        :tone="company.suspended ? 'warning' : 'success'"
                    />
                </li>
            </ul>
        </Card>

        <Card class="gap-0 p-0">
            <div class="flex items-center justify-between border-b px-5 py-4">
                <h2 class="text-base font-semibold">AI services</h2>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="aiEdit().url">Configure</Link>
                </Button>
            </div>
            <ul class="divide-y">
                <li
                    v-for="row in ai"
                    :key="row.key"
                    class="flex items-center justify-between gap-3 px-5 py-3"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ row.label }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ providerLabel(row.provider) }}
                            <template v-if="row.model">
                                · {{ row.model }}
                            </template>
                        </p>
                    </div>
                    <StatusBadge
                        :label="row.configured ? 'Ready' : 'Not set up'"
                        :tone="row.configured ? 'success' : 'warning'"
                    />
                </li>
            </ul>
        </Card>
    </div>

    <Card class="mt-6 gap-0 p-0">
        <div class="border-b px-5 py-4">
            <h2 class="text-base font-semibold">Recent platform activity</h2>
        </div>
        <p
            v-if="activity.length === 0"
            class="px-5 py-8 text-center text-sm text-muted-foreground"
        >
            Nothing has happened yet.
        </p>
        <ul v-else class="divide-y text-sm">
            <li
                v-for="entry in activity"
                :key="entry.id"
                class="flex flex-wrap items-center justify-between gap-2 px-5 py-3"
            >
                <span>
                    <span class="font-medium">{{ entry.by ?? 'System' }}</span>
                    {{ actionLabels[entry.action] ?? entry.action }}
                    <template v-if="entry.company">
                        <span class="text-muted-foreground"> · </span>
                        {{ entry.company }}
                    </template>
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ formatDateTime(entry.at) }}
                </span>
            </li>
        </ul>
    </Card>
</template>
