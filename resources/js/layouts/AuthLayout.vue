<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ToastHost from '@/components/ToastHost.vue';
import { Card } from '@/components/ui/card';
import { usePage } from '@inertiajs/vue3';

const appName = usePage().props.name;
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-background p-6"
    >
        <main class="w-full max-w-sm">
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <AppLogoIcon class="size-10" />
                <h1 class="text-xl/7 font-semibold">{{ appName }}</h1>
                <p class="text-sm text-muted-foreground">Sign in to continue</p>
            </div>
            <Card class="p-6">
                <slot />
            </Card>
        </main>
    </div>
    <ToastHost />
</template>
