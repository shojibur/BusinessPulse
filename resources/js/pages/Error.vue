<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
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
</script>

<template>
    <Head :title="message.title" />

    <Card class="mx-auto max-w-lg items-center gap-4 px-6 py-12 text-center">
        <p class="text-sm font-medium text-muted-foreground tabular-nums">
            Error {{ status }}
        </p>
        <h1 class="text-2xl/8 font-semibold">{{ message.title }}</h1>
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
