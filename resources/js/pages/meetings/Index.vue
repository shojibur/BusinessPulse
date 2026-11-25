<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Mic } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card } from '@/components/ui/card';
</script>

<template>
    <Head title="Meetings" />

    <!-- Placeholder: this section is not built yet and shows no business data. -->
    <PageHeader title="Meetings" />
    <Card>
        <EmptyState
            :icon="Mic"
            message="Meetings is not yet available. This section will be built in a later stage of the project."
        />
    </Card>
</template>
