<script setup lang="ts">
import FeedbackControl from '@/components/outcomes/FeedbackControl.vue';
import { Card } from '@/components/ui/card';
import type { FeedbackPanelData } from '@/types';

defineProps<{ feedback: FeedbackPanelData }>();
</script>

<template>
    <Card class="p-4">
        <FeedbackControl
            :submit-url="feedback.submitUrl"
            :mine="feedback.mine"
            :counts="feedback.counts"
            label="Was this finding useful?"
        />
    </Card>
</template>
