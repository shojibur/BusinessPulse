<script setup lang="ts">
import { BarChart, LineChart } from 'echarts/charts';
import {
    GridComponent,
    LegendComponent,
    TooltipComponent,
} from 'echarts/components';
import { init, use } from 'echarts/core';
import type { ECharts } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { onBeforeUnmount, useId, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { chartSeriesColors } from '@/lib/chartPalette';
import type { EChartProps } from '@/types';

// Register only the ECharts modules the application uses (tree-shaken, not the full bundle).
use([
    BarChart,
    LineChart,
    GridComponent,
    LegendComponent,
    TooltipComponent,
    CanvasRenderer,
]);

const props = withDefaults(defineProps<EChartProps>(), {
    emptyMessage: 'No data to display yet.',
});

const container = ref<HTMLDivElement | null>(null);
const descriptionId = useId();

let chart: ECharts | null = null;
let observer: ResizeObserver | null = null;

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function render(): void {
    chart?.setOption(
        {
            color: chartSeriesColors,
            animation: !prefersReducedMotion(),
            ...props.option,
        },
        true,
    );
}

function teardown(): void {
    observer?.disconnect();
    observer = null;
    chart?.dispose();
    chart = null;
}

// The container only exists while there is data to show (not when `isEmpty`).
watch(
    container,
    (element) => {
        teardown();

        if (!element) {
            return;
        }

        chart = init(element);
        render();

        observer = new ResizeObserver(() => chart?.resize());
        observer.observe(element);
    },
    { flush: 'post', immediate: true },
);

// A changed option updates the same chart instance; the container is not recreated.
watch(() => props.option, render, { deep: true });

onBeforeUnmount(teardown);
</script>

<template>
    <EmptyState v-if="isEmpty" :message="emptyMessage" />

    <figure v-else class="space-y-3">
        <div
            ref="container"
            role="img"
            :aria-label="ariaLabel"
            :aria-describedby="descriptionId"
            class="min-h-60 w-full"
        />
        <figcaption :id="descriptionId" class="sr-only">
            {{ ariaLabel }}
        </figcaption>

        <details v-if="tableData" class="text-sm">
            <summary
                class="cursor-pointer rounded-md text-primary-text focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                Show data as a table
            </summary>
            <Table class="mt-2">
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="column in tableData.columns"
                            :key="column"
                        >
                            {{ column }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(row, rowIndex) in tableData.rows"
                        :key="rowIndex"
                    >
                        <TableCell
                            v-for="(cell, cellIndex) in row"
                            :key="cellIndex"
                            class="tabular-nums"
                        >
                            {{ cell }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </details>
    </figure>
</template>
