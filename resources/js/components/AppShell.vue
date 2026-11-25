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
import {
    index as notificationsIndex,
    read,
    readAll,
} from '@/routes/notifications';
import type { NotificationItem } from '@/types';

const page = usePage();

// The list is rendered as given. S01 applies no role filtering (S02 adds it server-side).
const navigation = computed(() => page.props.navigation ?? []);

const appName = computed(() => page.props.name);

const user = computed(() => page.props.auth.user);

const unreadCount = computed(() => page.props.notifications?.unread_count ?? 0);
const notificationItems = computed(() => page.props.notifications?.items ?? []);

function openNotification(item: NotificationItem): void {
    if (!item.read) {
        router.post(read(item.id).url, {}, { preserveScroll: true });
    }

    if (item.url) {
        router.visit(item.url);
    }
}

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
                                <Link
                                    href="/today"
                                    class="touch-target hover:underline"
                                >
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

                    <!-- Notification bell: unread count and the latest notifications of the signed-in user. -->
                    <DropdownMenu v-if="user">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative"
                                :aria-label="`Notifications, ${unreadCount} unread`"
                            >
                                <Bell aria-hidden="true" />
                                <span
                                    v-if="unreadCount > 0"
                                    class="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-severity-high px-1 text-[10px] leading-4 font-semibold text-white tabular-nums"
                                    aria-hidden="true"
                                >
                                    {{ unreadCount }}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            class="w-80 max-w-[90vw]"
                        >
                            <DropdownMenuLabel
                                class="flex items-center justify-between font-normal"
                            >
                                <span class="text-sm font-semibold"
                                    >Notifications</span
                                >
                                <button
                                    v-if="unreadCount > 0"
                                    type="button"
                                    class="touch-target text-xs text-primary-text hover:underline"
                                    @click="
                                        router.post(
                                            readAll().url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    "
                                >
                                    Mark all read
                                </button>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <p
                                v-if="notificationItems.length === 0"
                                class="px-2 py-4 text-center text-sm text-muted-foreground"
                            >
                                No notifications.
                            </p>
                            <DropdownMenuItem
                                v-for="item in notificationItems"
                                :key="item.id"
                                class="flex-col items-start gap-0.5"
                                @select="openNotification(item)"
                            >
                                <span
                                    class="text-sm"
                                    :class="item.read ? '' : 'font-semibold'"
                                >
                                    {{ item.title }}
                                </span>
                                <span
                                    v-if="item.summary"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ item.summary }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                @select="router.visit(notificationsIndex().url)"
                            >
                                View all notifications
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

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
