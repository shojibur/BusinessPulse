<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import MeetingStatusBadge from '@/components/meetings/MeetingStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import type { IssueMeetings } from '@/types';

defineProps<{ meetings: IssueMeetings }>();
</script>

<template>
    <section class="space-y-3" aria-labelledby="related-meetings-heading">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="related-meetings-heading" class="text-lg/7 font-semibold">
                Meetings
            </h2>
            <Button variant="outline" size="sm" as-child>
                <Link :href="meetings.createUrl">Create meeting</Link>
            </Button>
        </div>
        <Card class="gap-0 p-0">
            <p
                v-if="meetings.items.length === 0"
                class="px-6 py-4 text-sm text-muted-foreground"
            >
                No meetings are linked to this issue yet.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="meeting in meetings.items"
                    :key="meeting.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 text-sm"
                >
                    <Link
                        :href="meeting.url"
                        class="touch-target font-medium text-primary-text hover:underline"
                        >{{ meeting.title }}</Link
                    >
                    <span class="flex items-center gap-2">
                        <span class="text-muted-foreground">{{
                            formatDate(meeting.meetingDate)
                        }}</span>
                        <MeetingStatusBadge
                            :status="meeting.status"
                            :label="meeting.statusLabel"
                        />
                    </span>
                </li>
            </ul>
        </Card>
    </section>
</template>
