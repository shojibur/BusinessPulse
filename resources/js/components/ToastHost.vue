<script lang="ts">
// Flash ids already shown, kept in memory for the page session. A page restored by
// back/forward navigation carries the same flash id and is therefore not re-shown.
const shownFlashIds = new Set<string>();
</script>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { useToast } from '@/composables/useToast';
import type { FlashMessage } from '@/types';

const page = usePage();
const toast = useToast();

function showOnce(flash: FlashMessage | null | undefined): void {
    if (!flash || shownFlashIds.has(flash.id)) {
        return;
    }

    shownFlashIds.add(flash.id);
    toast.show(flash.type, flash.message);
}

// The first flash is shown after mount so the Toaster is already subscribed.
onMounted(() => showOnce(page.props.flash));
watch(() => page.props.flash, showOnce);
</script>

<template>
    <!-- vue-sonner renders its toasts inside an aria-live="polite" region. -->
    <Toaster :duration="4000" close-button position="bottom-right" />
</template>
