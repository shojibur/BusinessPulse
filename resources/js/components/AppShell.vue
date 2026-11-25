<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Activity, Bell, LogOut, Menu, User } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ShellNav from '@/components/ShellNav.vue';
import ToastHost from '@/components/ToastHost.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';
import { logout } from '@/routes';

const page = usePage();

// The list is rendered as given. S01 applies no role filtering (S02 adds it server-side).
const navigation = computed(() => page.props.navigation ?? []);

const appName = computed(() => page.props.name);

const user = computed(() => page.props.auth.user);

const roleLabel = computed(() => {
    const role = user.value?.role;

    return role ? role.charAt(0).toUpperCase() + role.slice(1) : '';
});

function signOut(): void {
    router.post(logout().url);
}

const pageTitle = computed(
    () =>
        navigation.value.find((item) => item.active)?.label ??
        (page.component === 'Error' ? 'Error' : appName.value),
);

const mobileNavOpen = ref(false);

// Close the off-canvas navigation after every visit.
watch(
    () => page.url,
    () => {
        mobileNavOpen.value = false;
    },
);
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <a
            href="#main-content"
            class="sr-only z-50 rounded-md bg-surface px-4 py-2 text-sm font-medium text-primary-text focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:ring-2 focus:ring-ring focus:outline-none"
        >
            Skip to main content
        </a>

        <div class="min-h-svh">
            <!-- Sidebar: 248px above 1280px, icon rail from 769px to 1280px, hidden at 768px and below. -->
            <aside
                class="hidden bg-sidebar text-sidebar-foreground min-[769px]:fixed min-[769px]:inset-y-0 min-[769px]:left-0 min-[769px]:z-40 min-[769px]:flex min-[769px]:w-16 min-[769px]:flex-col min-[1281px]:w-[248px]"
            >
                <Link
                    href="/today"
                    class="flex h-14 items-center justify-center gap-3 px-0 text-white outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring min-[1281px]:justify-start min-[1281px]:px-4"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    >
                        <Activity class="size-5" aria-hidden="true" />
                    </span>
                    <span
                        class="sr-only text-base font-semibold min-[1281px]:not-sr-only"
                    >
                        {{ appName }}
                    </span>
                </Link>
                <nav aria-label="Primary" class="flex-1 overflow-y-auto p-2">
                    <ShellNav :items="navigation" />
                </nav>
            </aside>

            <div class="min-[769px]:pl-16 min-[1281px]:pl-[248px]">
                <header
                    class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b bg-surface px-4 min-[769px]:px-6"
                >
                    <Sheet v-model:open="mobileNavOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="min-[769px]:hidden"
                                aria-label="Open navigation menu"
                            >
                                <Menu aria-hidden="true" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="w-72 border-sidebar-border bg-sidebar p-0 text-sidebar-foreground"
                        >
                            <SheetHeader class="h-14 justify-center px-4">
                                <SheetTitle class="text-white">
                                    {{ appName }}
                                </SheetTitle>
                                <SheetDescription class="sr-only">
                                    Primary navigation
                                </SheetDescription>
                            </SheetHeader>
                            <nav aria-label="Primary" class="p-2">
                                <ShellNav :items="navigation" mode="full" />
                            </nav>
                        </SheetContent>
                    </Sheet>

                    <nav aria-label="Breadcrumb" class="min-w-0 flex-1">
                        <ol class="flex items-center gap-2 text-sm">
                            <li class="hidden text-muted-foreground sm:block">
                                <Link href="/today" class="hover:underline">
                                    {{ appName }}
                                </Link>
                            </li>
                            <li
                                class="hidden text-muted-foreground sm:block"
                                aria-hidden="true"
                            >
                                /
                            </li>
                            <li
                                class="truncate font-semibold text-foreground"
                                aria-current="page"
                            >
                                {{ pageTitle }}
                            </li>
                        </ol>
                    </nav>

                    <!-- The notification bell is a placeholder until notifications exist (later spec). -->
                    <Button
                        variant="ghost"
                        size="icon"
                        disabled
                        aria-label="Notifications (not yet available)"
                    >
                        <Bell aria-hidden="true" />
                    </Button>
                    <DropdownMenu v-if="user">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Account menu for ${user.name}`"
                            >
                                <User aria-hidden="true" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-60">
                            <DropdownMenuLabel class="font-normal">
                                <p class="truncate text-sm font-semibold">
                                    {{ user.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ roleLabel }}
                                    <template v-if="user.department">
                                        · {{ user.department.name }}
                                    </template>
                                </p>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @select="signOut">
                                <LogOut aria-hidden="true" />
                                Sign out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>

                <main
                    id="main-content"
                    tabindex="-1"
                    class="mx-auto w-full max-w-[1280px] p-4 outline-none min-[769px]:p-6"
                >
                    <slot />
                </main>
            </div>
        </div>

        <ToastHost />
    </TooltipProvider>
</template>
