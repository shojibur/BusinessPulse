<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Shield } from '@lucide/vue';
import { computed } from 'vue';
import AppSidebarShell from '@/components/AppSidebarShell.vue';
import { logout, dashboard } from '@/routes/platform';
import { edit as aiEdit } from '@/routes/platform/ai';
import { index as companiesIndex } from '@/routes/platform/companies';
import type { NavItem } from '@/types';

const page = usePage();
const admin = computed(() => page.props.platformAdmin);

const path = (url: string): string => url.split('?')[0];

// The platform has three sections. Active means the current URL is the section or inside it.
const navigation = computed<NavItem[]>(() => {
    const current = path(page.url);
    const overview = dashboard().url;
    const companies = companiesIndex().url;
    const ai = aiEdit().url;

    return [
        {
            label: 'Overview',
            href: overview,
            icon: 'LayoutDashboard',
            active: current === overview,
        },
        {
            label: 'Companies',
            href: companies,
            icon: 'Building2',
            active:
                current === companies || current.startsWith(`${companies}/`),
        },
        {
            label: 'AI settings',
            href: ai,
            icon: 'Sparkles',
            active: current === ai,
        },
    ];
});

const pageTitle = computed(
    () => navigation.value.find((item) => item.active)?.label ?? 'Platform',
);

function signOut(): void {
    router.post(logout().url);
}
</script>

<template>
    <AppSidebarShell
        :brand-href="dashboard().url"
        brand-name="BusinessPulse"
        brand-subtitle="Platform admin"
        :brand-icon="Shield"
        :navigation="navigation"
        breadcrumb-root="Platform"
        :page-title="pageTitle"
        :user-name="admin?.name"
        :user-subtitle="admin?.email"
        @sign-out="signOut"
    >
        <slot />
    </AppSidebarShell>
</template>
