<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ImportErrorRow, Pagination } from '@/types';

defineProps<{ errors: ImportErrorRow[]; pagination: Pagination }>();
</script>

<template>
    <EmptyState
        v-if="pagination.total === 0"
        :icon="CircleCheck"
        message="No row errors. Every row in the file passed validation."
    />

    <div v-else class="space-y-3">
        <div class="overflow-x-auto rounded-lg border bg-surface">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-20">Row</TableHead>
                        <TableHead>Column</TableHead>
                        <TableHead>Code</TableHead>
                        <TableHead>Problem</TableHead>
                        <TableHead>Value in file</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="error in errors" :key="error.id">
                        <TableCell class="tabular-nums">
                            {{ error.row_number }}
                        </TableCell>
                        <TableCell>{{ error.column ?? '—' }}</TableCell>
                        <TableCell>
                            <code class="text-xs">{{ error.code }}</code>
                        </TableCell>
                        <TableCell>{{ error.message }}</TableCell>
                        <!-- File content is shown as escaped text only. -->
                        <TableCell
                            class="max-w-xs break-all text-muted-foreground"
                        >
                            {{
                                error.column &&
                                error.raw_row[error.column] !== undefined
                                    ? error.raw_row[error.column] || '(empty)'
                                    : '—'
                            }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <nav
            v-if="pagination.last_page > 1"
            aria-label="Error pages"
            class="flex items-center justify-between text-sm"
        >
            <span class="text-muted-foreground tabular-nums">
                Page {{ pagination.current_page }} of
                {{ pagination.last_page }} ({{ pagination.total }} errors)
            </span>
            <span class="flex gap-4">
                <Link
                    v-if="pagination.prev_url"
                    :href="pagination.prev_url"
                    class="touch-target font-medium text-primary-text hover:underline"
                    preserve-scroll
                >
                    Previous
                </Link>
                <Link
                    v-if="pagination.next_url"
                    :href="pagination.next_url"
                    class="touch-target font-medium text-primary-text hover:underline"
                    preserve-scroll
                >
                    Next
                </Link>
            </span>
        </nav>
    </div>
</template>
