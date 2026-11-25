<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import type { SettingsTab } from '@/types';

const page = usePage();

// Tabs come from the server (App\Support\SettingsTabs), already filtered by policy.
const tabs = computed(
    () => (page.props.settingsTabs as SettingsTab[] | undefined) ?? [],
);
</script>

<template>
    <PageHeader
        title="Settings"
        description="Company, staff and department information."
    />

    <nav aria-label="Settings sections" class="mb-6 border-b">
        <ul class="-mb-px flex gap-1 overflow-x-auto">
            <li v-for="tab in tabs" :key="tab.href">
                <Link
                    :href="tab.href"
                    :aria-current="tab.active ? 'page' : undefined"
                    class="inline-flex h-10 items-center border-b-2 px-4 text-sm font-medium whitespace-nowrap outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    :class="
                        tab.active
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ tab.label }}
                </Link>
            </li>
        </ul>
    </nav>

    <slot />
</template>
