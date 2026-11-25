import type { Component } from 'vue';
import type { EChartsCoreOption } from 'echarts/core';

export type Severity = 'high' | 'medium' | 'low';

export type StatusTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

export type FlashMessage = {
    id: string;
    type: 'success' | 'error' | 'info' | 'warning';
    message: string;
};

export type PageHeaderProps = {
    title: string;
    description?: string;
};

export type KpiChange = {
    direction: 'up' | 'down' | 'flat';
    /** Signed text such as "+4.2%". The direction is also shown as an icon, never by color alone. */
    text: string;
};

export type KpiCardProps = {
    label: string;
    value: string;
    change?: KpiChange;
    caption?: string;
    loading?: boolean;
};

export type SeverityBadgeProps = {
    severity: Severity;
};

export type StatusBadgeProps = {
    label: string;
    tone: StatusTone;
};

export type EmptyStateProps = {
    icon?: Component;
    message: string;
};

export type ErrorStateProps = {
    icon?: Component;
    message: string;
    retryLabel?: string;
};

export type EChartTableData = {
    columns: string[];
    rows: (string | number)[][];
};

export type EChartProps = {
    option: EChartsCoreOption;
    /** Required text alternative, announced as the accessible description of the chart. */
    ariaLabel: string;
    /** When given, a data table equivalent of the chart is rendered. */
    tableData?: EChartTableData;
    /** When true, an EmptyState replaces the chart. */
    isEmpty?: boolean;
    emptyMessage?: string;
};
