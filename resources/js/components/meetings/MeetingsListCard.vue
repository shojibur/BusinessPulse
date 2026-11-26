<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    Clock,
    Plus,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    groupLabels,
    groupOf,
    groupOrder,
} from '@/components/meetings/meetingGroups';
import type { MeetingGroup } from '@/components/meetings/meetingGroups';
import SectionHeader from '@/components/SectionHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/format';
import { create } from '@/routes/meetings';
import type { MeetingSidebarItem } from '@/types';

const props = defineProps<{
    items: MeetingSidebarItem[];
    businessToday: string;
    selectedId: number | null;
    canCreate?: boolean;
}>();

const grouped = computed(() => {
    const result: Record<MeetingGroup, MeetingSidebarItem[]> = {
        upcoming: [],
        in_progress: [],
        completed: [],
    };

    for (const item of props.items) {
        result[groupOf(item, props.businessToday)].push(item);
    }

    return result;
});

// Open on the tab of the selected meeting, else the first tab that has meetings.
function initialTab(): MeetingGroup {
    const selected = props.items.find((item) => item.id === props.selectedId);

    if (selected) {
        return groupOf(selected, props.businessToday);
    }

    return groupOrder.find((g) => grouped.value[g].length > 0) ?? 'completed';
}

const tab = ref<MeetingGroup>(initialTab());

watch(
    () => props.selectedId,
    () => {
        tab.value = initialTab();
    },
);

const statusIcon = (status: MeetingSidebarItem['status']) =>
    status === 'ready_for_review'
        ? CircleCheck
        : status === 'failed'
          ? CircleAlert
          : status === 'draft'
            ? CircleDashed
            : Clock;

const statusColor = (status: MeetingSidebarItem['status']): string =>
    status === 'ready_for_review'
        ? 'text-success'
        : status === 'failed'
          ? 'text-severity-high'
          : 'text-muted-foreground';
</script>

<template>
    <Card class="gap-0 p-0">
        <div class="space-y-4 border-b p-5">
            <SectionHeader
                title="Management meetings"
                :description="`${items.length} meeting${items.length === 1 ? '' : 's'}`"
                :icon="CalendarDays"
            >
                <Button v-if="canCreate" size="sm" variant="outline" as-child>
                    <Link :href="create().url">
                        <Plus aria-hidden="true" />
                        New
                    </Link>
                </Button>
            </SectionHeader>
            <Tabs v-model="tab">
                <TabsList class="w-full">
                    <TabsTrigger
                        v-for="group in groupOrder"
                        :key="group"
                        :value="group"
                        class="flex-1"
                    >
                        {{ groupLabels[group] }} ({{ grouped[group].length }})
                    </TabsTrigger>
                </TabsList>
                <TabsContent
                    v-for="group in groupOrder"
                    :key="group"
                    :value="group"
                    class="mt-0"
                />
            </Tabs>
        </div>

        <p
            v-if="grouped[tab].length === 0"
            class="px-5 py-8 text-center text-sm text-muted-foreground"
        >
            No {{ groupLabels[tab].toLowerCase() }} meetings.
        </p>
        <ul v-else class="divide-y">
            <li v-for="meeting in grouped[tab]" :key="meeting.id">
                <Link
                    :href="meeting.url"
                    :aria-current="
                        meeting.id === selectedId ? 'page' : undefined
                    "
                    class="flex items-center gap-3 border-l-2 px-5 py-3.5 transition-colors outline-none hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset"
                    :class="
                        meeting.id === selectedId
                            ? 'border-l-primary bg-primary/5'
                            : 'border-l-transparent'
                    "
                >
                    <component
                        :is="statusIcon(meeting.status)"
                        class="size-5 shrink-0"
                        :class="statusColor(meeting.status)"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-sm font-medium text-foreground"
                            >{{ meeting.title }}</span
                        >
                        <span
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{ formatDate(meeting.meetingDate) }} ·
                            {{ meeting.departmentName }} ·
                            {{ meeting.statusLabel }}
                        </span>
                    </span>
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>
    </Card>
</template>
