<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BellOff } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SeverityBadge from '@/components/SeverityBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { read, readAll } from '@/routes/notifications';
import type { NotificationItem, Pagination } from '@/types';

defineProps<{
    notifications: NotificationItem[];
    unreadCount: number;
    pagination: Pagination;
}>();
</script>

<template>
    <Head title="Notifications" />

    <PageHeader title="Notifications" :description="`${unreadCount} unread`">
        <Button
            v-if="unreadCount > 0"
            variant="outline"
            @click="router.post(readAll().url, {}, { preserveScroll: true })"
        >
            Mark all as read
        </Button>
    </PageHeader>

    <Card v-if="notifications.length === 0">
        <EmptyState :icon="BellOff" message="You have no notifications." />
    </Card>

    <ul v-else class="space-y-3">
        <li v-for="item in notifications" :key="item.id">
            <Card class="gap-2 p-4" :class="item.read ? 'opacity-70' : ''">
                <div class="flex flex-wrap items-center gap-2">
                    <SeverityBadge
                        v-if="
                            item.severity === 'high' ||
                            item.severity === 'medium' ||
                            item.severity === 'low'
                        "
                        :severity="item.severity"
                    />
                    <span
                        v-if="!item.read"
                        class="text-xs font-medium text-primary-text"
                    >
                        Unread
                    </span>
                </div>
                <p class="font-medium">
                    <Link
                        v-if="item.url"
                        :href="item.url"
                        class="touch-target text-primary-text hover:underline"
                    >
                        {{ item.title }}
                    </Link>
                    <template v-else>{{ item.title }}</template>
                </p>
                <p v-if="item.summary" class="text-sm text-muted-foreground">
                    {{ item.summary }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ item.department ?? '' }} ·
                    {{ formatDateTime(item.created_at) }}
                </p>
                <Button
                    v-if="!item.read"
                    size="sm"
                    variant="outline"
                    class="w-fit"
                    @click="
                        router.post(
                            read(item.id).url,
                            {},
                            { preserveScroll: true },
                        )
                    "
                >
                    Mark as read
                </Button>
            </Card>
        </li>
    </ul>

    <nav
        v-if="pagination.last_page > 1"
        aria-label="Notification pages"
        class="mt-4 flex justify-between text-sm"
    >
        <Link
            v-if="pagination.prev_url"
            :href="pagination.prev_url"
            class="touch-target text-primary-text"
            >Previous</Link
        >
        <Link
            v-if="pagination.next_url"
            :href="pagination.next_url"
            class="touch-target ml-auto text-primary-text"
            >Next</Link
        >
    </nav>
</template>
