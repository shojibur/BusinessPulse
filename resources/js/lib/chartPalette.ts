/** Chart palette from docs/ui-ux-design-system.md section 2. */
export const chartPalette = {
    teal: '#0F9488',
    slate: '#334155',
    amber: '#D97706',
    red: '#DC2626',
    lightTeal: '#99D6CF',
} as const;

/** Default series color order for charts. */
export const chartSeriesColors: string[] = [
    chartPalette.teal,
    chartPalette.slate,
    chartPalette.amber,
    chartPalette.red,
    chartPalette.lightTeal,
];
