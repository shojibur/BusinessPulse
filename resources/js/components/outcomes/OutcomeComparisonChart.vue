<script setup lang="ts">
import { computed } from 'vue';
import EChart from '@/components/EChart.vue';
import { chartPalette } from '@/lib/chartPalette';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    categories: string[];
    values: (string | null)[];
    unit: 'MYR' | '%';
    metricLabel: string;
}>();

const text = (v: string | null) =>
    v === null
        ? '—'
        : props.unit === 'MYR'
          ? formatMoney(v).replace('RM', 'MYR')
          : `${v}%`;

const option = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: 8, right: 8, top: 16, bottom: 8, containLabel: true },
    xAxis: { type: 'category', data: props.categories },
    yAxis: { type: 'value' },
    series: [
        {
            name: props.metricLabel,
            type: 'bar',
            data: props.values.map((v) => (v === null ? null : Number(v))),
            itemStyle: { color: chartPalette.teal },
        },
    ],
}));

const table = computed(() => ({
    columns: ['Period', props.metricLabel],
    rows: props.categories.map((c, i) => [c, text(props.values[i] ?? null)]),
}));
</script>

<template>
    <EChart
        :option="option"
        :ariaLabel="`${metricLabel} for the compared periods`"
        :table-data="table"
    />
</template>
