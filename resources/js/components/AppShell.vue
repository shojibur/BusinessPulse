<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Activity, Bell } from '@lucide/vue';
import { computed } from 'vue';
import AppSidebarShell from '@/components/AppSidebarShell.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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

// Shown under the name in the sidebar: the role and, when there is one, the department.
const userSubtitle = computed(() =>
    [roleLabel.value, user.value?.department?.name].filter(Boolean).join(' · '),
);

function signOut(): void {
    router.post(logout().url);
}

const pageTitle = computed(
    () =>
        navigation.value.find((item) => item.active)?.label ??
        (page.component === 'Error' ? 'Error' : appName.value),
);
</script>

<template>
    <AppSidebarShell
        brand-href="/today"
        :brand-name="appName"
        :brand-icon="Activity"
        :navigation="navigation"
        :breadcrumb-root="appName"
        :page-title="pageTitle"
        :user-name="user?.name"
        :user-subtitle="userSubtitle"
        :context-name="page.props.company?.name"
        :date-label="page.props.businessDate"
        @sign-out="signOut"
    >
        <template #header-actions>
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
                <DropdownMenuContent align="end" class="w-80 max-w-[90vw]">
                    <DropdownMenuLabel
                        class="flex items-center justify-between font-normal"
                    >
                        <span class="text-sm font-semibold">Notifications</span>
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
        </template>

        <slot />
    </AppSidebarShell>
</template>
