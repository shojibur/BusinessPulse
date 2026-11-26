<script setup lang="ts">
import { computed } from 'vue';
import EChart from '@/components/EChart.vue';
import { chartPalette } from '@/lib/chartPalette';
import { formatMoney } from '@/lib/format';
import type { PhaseCode } from '@/types';

const props = defineProps<{
    categories: string[];
    values: (string | null)[];
    unit: 'MYR' | '%';
    metricLabel: string;
    /** The phase of the compared (last) period: it sets the colour of the last bar. */
    phase?: PhaseCode;
}>();

const text = (v: string | null) =>
    v === null ? '—' : props.unit === 'MYR' ? formatMoney(v) : `${v}%`;

// One colour per period: the week before the decline, the issue baseline, then the compared period by phase.
const followupColor = computed(() =>
    props.phase === 'post_action' ? '#0d7c72' : chartPalette.teal,
);

const colors = computed(() => {
    const last = followupColor.value;

    return props.categories.length === 3
        ? [chartPalette.lightTeal, chartPalette.amber, last]
        : [chartPalette.amber, last];
});

const legend = computed(() => {
    const names =
        props.categories.length === 3
            ? ['Before the decline', 'Issue baseline']
            : ['Issue baseline'];
    const last =
        props.phase === 'post_action'
            ? 'Post-action'
            : props.phase === 'during_action'
              ? 'During action'
              : 'Compared period';

    return [...names, last].map((name, index) => ({
        name,
        color: colors.value[index],
    }));
});

const option = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: 8, right: 8, top: 28, bottom: 8, containLabel: true },
    xAxis: {
        type: 'category',
        data: props.categories,
        axisTick: { show: false },
        axisLabel: { color: '#475569' },
    },
    yAxis: {
        type: 'value',
        splitLine: { lineStyle: { color: '#e5eaf0' } },
        axisLabel: {
            color: '#5b6b80',
            formatter: (value: number) =>
                props.unit === 'MYR'
                    ? `RM ${value.toLocaleString('en-US')}`
                    : `${value}%`,
        },
    },
    series: [
        {
            name: props.metricLabel,
            type: 'bar',
            barMaxWidth: 64,
            data: props.values.map((v, index) => ({
                value: v === null ? null : Number(v),
                itemStyle: {
                    color: colors.value[index],
                    borderRadius: [8, 8, 0, 0],
                },
            })),
            label: {
                show: true,
                position: 'top',
                color: '#0f172a',
                fontWeight: 600,
                formatter: (params: { dataIndex: number }) =>
                    text(props.values[params.dataIndex] ?? null),
            },
        },
    ],
}));

const table = computed(() => ({
    columns: ['Period', props.metricLabel],
    rows: props.categories.map((c, i) => [c, text(props.values[i] ?? null)]),
}));
</script>

<template>
    <div class="space-y-3">
        <EChart
            :option="option"
            :ariaLabel="`${metricLabel} for the compared periods`"
            :table-data="table"
        />
        <ul
            class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground"
        >
            <li
                v-for="item in legend"
                :key="item.name"
                class="flex items-center gap-1.5"
            >
                <span
                    class="size-2.5 rounded-sm"
                    :style="{ backgroundColor: item.color }"
                    aria-hidden="true"
                />
                {{ item.name }}
            </li>
        </ul>
    </div>
</template>
