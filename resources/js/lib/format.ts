/**
 * Money and number formatting. Western digits everywhere, per spec.
 */

const amountFormatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const intFormatter = new Intl.NumberFormat('en-US');

/** 1234.5 -> "1,234.50" */
export function fmtAmount(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const n = typeof value === 'number' ? value : parseFloat(value);
    if (Number.isNaN(n)) return '—';
    return amountFormatter.format(n);
}

/** Unit price: show up to 4 decimals, trimming trailing zeros past 2. "0.3000" -> "0.30", "0.2750" -> "0.275" */
export function fmtPrice(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const n = typeof value === 'number' ? value : parseFloat(value);
    if (Number.isNaN(n)) return '—';
    const four = n.toFixed(4);
    return four.replace(/(\.\d{2}\d*?)0+$/, '$1');
}

export function fmtInt(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return intFormatter.format(value);
}
