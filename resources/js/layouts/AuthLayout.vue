<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { BarChart3, ShieldCheck, Sparkles } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ToastHost from '@/components/ToastHost.vue';

const appName = usePage().props.name;

const points = [
    { icon: BarChart3, text: 'Your sales numbers, checked every week' },
    { icon: Sparkles, text: 'Problems found early, with the evidence' },
    { icon: ShieldCheck, text: 'Every action approved by a person' },
];
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <!-- Brand panel: wide screens only. -->
        <aside
            class="relative hidden flex-col justify-between overflow-hidden bg-sidebar p-12 text-sidebar-foreground lg:flex"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-primary/20 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -bottom-32 -left-16 size-96 rounded-full bg-sidebar-ring/10 blur-3xl"
                aria-hidden="true"
            />
            <div class="relative flex items-center gap-3 text-white">
                <AppLogoIcon class="size-9" />
                <span class="text-lg font-semibold">{{ appName }}</span>
            </div>
            <div class="relative space-y-8">
                <div class="space-y-3">
                    <p
                        class="text-3xl/10 font-semibold tracking-tight text-white"
                    >
                        Know what is happening in your business, and what to do
                        about it.
                    </p>
                    <p class="max-w-md text-sm/6">
                        Detect, understand, decide, execute and verify, in one
                        place.
                    </p>
                </div>
                <ul class="space-y-4">
                    <li
                        v-for="point in points"
                        :key="point.text"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sidebar-ring"
                        >
                            <component
                                :is="point.icon"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        {{ point.text }}
                    </li>
                </ul>
            </div>
            <p class="relative text-xs">Fictional demonstration data.</p>
        </aside>

        <main
            class="flex flex-col items-center justify-center bg-background p-6 sm:p-12"
        >
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <AppLogoIcon class="size-9" />
                    <span class="text-lg font-semibold">{{ appName }}</span>
                </div>
                <div class="mb-6 space-y-1">
                    <h1 class="text-2xl/8 font-semibold tracking-tight">
                        Welcome back
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Sign in to continue
                    </p>
                </div>
                <slot />
            </div>
        </main>
    </div>
    <ToastHost />
</template>
