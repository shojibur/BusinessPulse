<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ToastHost from '@/components/ToastHost.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/platform/companies';
import { logout } from '@/routes/platform';

function signOut(): void {
    router.post(logout().url);
}
</script>

<template>
    <div class="min-h-svh bg-background">
        <header class="border-b bg-card">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-3"
            >
                <Link
                    :href="index().url"
                    class="flex items-center gap-2 font-semibold"
                >
                    <AppLogoIcon class="size-6" />
                    BusinessPulse Platform
                </Link>
                <Button variant="outline" size="sm" @click="signOut"
                    >Sign out</Button
                >
            </div>
        </header>
        <main class="mx-auto max-w-5xl p-6"><slot /></main>
    </div>
    <ToastHost />
</template>
