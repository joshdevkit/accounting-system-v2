/**
 * Formatting helpers shared by the ledger and report screens.
 *
 * Money leaves the server as integer centavos (exact, locale agnostic) or as
 * a `decimal:2` string straight from a model cast, and both are handled here
 * so no component ever has to think about it.
 */

export type MoneyInput = number | string | null | undefined;

export function toNumber(value: MoneyInput): number {
    if (value === null || value === undefined || value === '') return 0;

    const parsed = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

export function centsToAmount(cents: number): number {
    return (Number.isFinite(cents) ? cents : 0) / 100;
}

export function amountToCents(amount: MoneyInput): number {
    return Math.round(toNumber(amount) * 100);
}

export function formatMoney(value: MoneyInput, currency = 'PHP'): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(toNumber(value));
}

export function formatCents(cents: number, currency = 'PHP'): string {
    return formatMoney(centsToAmount(cents), currency);
}

/**
 * Signed amounts are shown in parentheses, the way accounting printouts do.
 */
export function formatCentsSigned(cents: number, currency = 'PHP'): string {
    if (cents < 0) {
        return `(${formatCents(-cents, currency)})`;
    }

    return formatCents(cents, currency);
}

export function formatPercent(part: number, whole: number): string {
    if (!whole) return '0%';

    return `${Math.round((part / whole) * 100)}%`;
}

/**
 * `2026-01-31T00:00:00.000000Z` -> `31 Jan 2026`, without letting the browser
 * shift a pure date across time zones.
 */
export function parseDateKey(value: MoneyInput): string {
    const raw = typeof value === 'string' ? value : String(value ?? '');
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (match) return `${match[1]}-${match[2]}-${match[3]}`;

    const date = new Date(raw);

    if (Number.isNaN(date.getTime())) return '';

    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

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

export function formatDate(value: MoneyInput): string {
    const key = parseDateKey(value);

    if (!key) return '—';

    const [year, month, day] = key.split('-').map(Number);

    return `${day} ${MONTHS[(month ?? 1) - 1]} ${year}`;
}

export function formatDateTime(value: MoneyInput): string {
    const raw = typeof value === 'string' ? value : String(value ?? '');
    const key = parseDateKey(raw);

    if (!key) return '—';

    const time = raw.match(/T(\d{2}):(\d{2})/);
    const day = formatDate(raw);

    return time ? `${day}, ${time[1]}:${time[2]}` : day;
}

export function todayKey(): string {
    const now = new Date();
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export function sumCents(values: number[]): number {
    return values.reduce((total, value) => total + Math.round(value), 0);
}


export function toTitleCase(value: string): string {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}


export function uppercase(value: string): string {
    return value.toUpperCase();
}