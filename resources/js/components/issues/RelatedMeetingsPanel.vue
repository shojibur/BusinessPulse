<script setup lang="ts">
import SectionHeader from '@/components/SectionHeader.vue';
import { Mic } from '@lucide/vue';
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
        <SectionHeader
            id="related-meetings-heading"
            title="Meetings"
            description="Discussions linked to this issue."
            :icon="Mic"
        >
            <Button variant="outline" size="sm" as-child>
                <Link :href="meetings.createUrl">Create meeting</Link>
            </Button>
        </SectionHeader>
        <Card class="gap-0 p-0">
            <div
                v-if="meetings.items.length === 0"
                class="flex flex-col items-center gap-1 px-6 py-8 text-center"
            >
                <Mic class="size-6 text-muted-foreground" aria-hidden="true" />
                <p class="text-sm text-muted-foreground">
                    No meetings are linked to this issue yet.
                </p>
            </div>
            <ul v-else class="divide-y">
                <li
                    v-for="meeting in meetings.items"
                    :key="meeting.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5 text-sm"
                >
                    <Link
                        :href="meeting.url"
                        class="touch-target font-medium text-primary-text hover:underline"
                        >{{ meeting.title }}</Link
                    >
                    <span class="flex items-center gap-2">
                        <span class="text-xs text-muted-foreground">{{
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
