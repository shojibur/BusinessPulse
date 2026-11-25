<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppShell from '@/components/AppShell.vue';
import ToastHost from '@/components/ToastHost.vue';

const page = usePage();
const signedIn = computed(() => page.props.auth.user !== null);
</script>

<template>
    <AppShell v-if="signedIn">
        <slot />
    </AppShell>

    <!-- Guests only see error pages (for example a 404) outside the application shell. -->
    <div v-else class="min-h-svh bg-background p-6">
        <main class="mx-auto max-w-lg"><slot /></main>
        <ToastHost />
    </div>
</template>
