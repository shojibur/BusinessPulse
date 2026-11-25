const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/** "2026-09-21" -> "21 Sep 2026". Calendar dates carry no timezone, so no Date object is involved. */
export function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    const [year, month, day] = date.split('-').map(Number);

    return `${day} ${MONTHS[month - 1]} ${year}`;
}

/** "2026-09-21" and "2026-09-26" -> "21–26 Sep" (or "28 Sep–3 Oct" across months). */
export function formatDateRange(from: string, to: string): string {
    const [, fromMonth, fromDay] = from.split('-').map(Number);
    const [, toMonth, toDay] = to.split('-').map(Number);

    return fromMonth === toMonth
        ? `${fromDay}–${toDay} ${MONTHS[toMonth - 1]}`
        : `${fromDay} ${MONTHS[fromMonth - 1]}–${toDay} ${MONTHS[toMonth - 1]}`;
}

/** An ISO 8601 string already in the company timezone -> "21 Sep 2026, 09:00" (no browser timezone shift). */
export function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return `${formatDate(iso.slice(0, 10))}, ${iso.slice(11, 16)}`;
}

export function formatBytes(bytes: number | null): string {
    if (bytes === null) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return bytes < 1048576
        ? `${(bytes / 1024).toFixed(1)} KB`
        : `${(bytes / 1048576).toFixed(1)} MB`;
}

export function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-MY').format(value);
}

/** "124000.00" -> "RM 124,000.00" (string work only: money is never turned into a float). */
export function formatMoney(amount: string | null): string {
    if (amount === null) {
        return '—';
    }

    const negative = amount.startsWith('-');
    const [whole, fraction = '00'] = amount.replace('-', '').split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `${negative ? '-' : ''}RM ${grouped}.${fraction.padEnd(2, '0')}`;
}

/** An ISO 8601 timestamp already in the company timezone -> "28 Sep 2026, 09:00". */
export const formatTimestamp = formatDateTime;
