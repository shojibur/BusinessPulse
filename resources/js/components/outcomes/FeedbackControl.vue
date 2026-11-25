<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ThumbsDown, ThumbsUp } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    submitUrl: string;
    mine: boolean | null;
    counts: { useful: number; notUseful: number };
    label: string;
}>();
const busy = ref(false);

function vote(useful: boolean): void {
    busy.value = true;
    router.post(
        props.submitUrl,
        { useful },
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}
</script>

<template>
    <div class="space-y-2">
        <p class="text-sm font-medium">{{ label }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <Button
                :variant="mine === true ? 'default' : 'outline'"
                size="sm"
                :disabled="busy"
                :aria-pressed="mine === true"
                @click="vote(true)"
            >
                <ThumbsUp aria-hidden="true" />
                Useful
            </Button>
            <Button
                :variant="mine === false ? 'default' : 'outline'"
                size="sm"
                :disabled="busy"
                :aria-pressed="mine === false"
                @click="vote(false)"
            >
                <ThumbsDown aria-hidden="true" />
                Not useful
            </Button>
            <span class="text-xs text-muted-foreground tabular-nums"
                >{{ counts.useful }} useful · {{ counts.notUseful }} not
                useful</span
            >
        </div>
        <p class="text-xs text-muted-foreground">
            Feedback is recorded for people to review. It does not change any
            rule or result.
        </p>
    </div>
</template>
