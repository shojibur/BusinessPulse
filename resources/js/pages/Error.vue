<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    FileQuestion,
    LockKeyhole,
    ServerCrash,
    TimerReset,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

const props = defineProps<{ status: number }>();

const messages: Record<number, { title: string; explanation: string }> = {
    403: {
        title: 'Access denied',
        explanation: 'You do not have permission to view this page.',
    },
    404: {
        title: 'Page not found',
        explanation:
            'The page you are looking for does not exist or was moved.',
    },
    419: {
        title: 'Session expired',
        explanation:
            'Your session expired for security reasons. Reload the page and try again.',
    },
    500: {
        title: 'Something went wrong',
        explanation:
            'An unexpected error occurred on our side. Please try again in a moment.',
    },
    503: {
        title: 'Down for maintenance',
        explanation: 'BusinessPulse is being updated. Please try again later.',
    },
};

const message = computed(() => messages[props.status] ?? messages[500]);

function reload(): void {
    window.location.reload();
}

// One icon per kind of error, so the page is friendly at a glance.
const icon = computed<Component>(
    () =>
        ({
            403: LockKeyhole,
            404: FileQuestion,
            419: TimerReset,
        })[props.status] ?? ServerCrash,
);
</script>

<template>
    <Head :title="message.title" />

    <Card
        class="mx-auto mt-12 max-w-lg items-center gap-4 px-6 py-14 text-center"
    >
        <span
            class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary-text"
        >
            <component :is="icon" class="size-7" aria-hidden="true" />
        </span>
        <p
            class="text-xs font-medium tracking-wide text-muted-foreground uppercase tabular-nums"
        >
            Error {{ status }}
        </p>
        <h1 class="text-2xl/8 font-semibold tracking-tight">
            {{ message.title }}
        </h1>
        <p class="max-w-sm text-sm text-muted-foreground">
            {{ message.explanation }}
        </p>
        <div class="mt-2 flex flex-wrap justify-center gap-2">
            <Button v-if="status === 419" @click="reload">Reload page</Button>
            <Button :variant="status === 419 ? 'outline' : 'default'" as-child>
                <Link href="/today">Go to Today</Link>
            </Button>
        </div>
    </Card>
</template>
