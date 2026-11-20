/**
 * Mirrors FiscalYearService::generatePeriods() so the form can show the
 * periods a fiscal year will produce before anything is saved.
 */

export type PeriodPreview = {
    number: number;
    name: string;
    start_date: string;
    end_date: string;
};

type Parts = { year: number; month: number; day: number };

const pad = (value: number) => String(value).padStart(2, '0');

function parseDateKey(key: string): Parts | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(key ?? '');

    if (!match) return null;

    return {
        year: Number(match[1]),
        month: Number(match[2]) - 1,
        day: Number(match[3]),
    };
}

function toKey(parts: Parts): string {
    return `${parts.year}-${pad(parts.month + 1)}-${pad(parts.day)}`;
}

function addMonths(parts: Parts, months: number): Parts {
    const date = new Date(
        Date.UTC(parts.year, parts.month + months, parts.day),
    );

    return {
        year: date.getUTCFullYear(),
        month: date.getUTCMonth(),
        day: date.getUTCDate(),
    };
}

function addDays(parts: Parts, days: number): Parts {
    const date = new Date(Date.UTC(parts.year, parts.month, parts.day + days));

    return {
        year: date.getUTCFullYear(),
        month: date.getUTCMonth(),
        day: date.getUTCDate(),
    };
}

function compare(left: Parts, right: Parts): number {
    return toKey(left).localeCompare(toKey(right));
}

function labelFor(parts: Parts): string {
    return new Date(Date.UTC(parts.year, parts.month, 1)).toLocaleDateString(
        'en-US',
        {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        },
    );
}

export function previewPeriods(
    startDate: string,
    endDate: string,
    count = 12,
): PeriodPreview[] {
    const start = parseDateKey(startDate);
    const end = parseDateKey(endDate);

    if (!start || !end || compare(start, end) > 0) return [];

    const periods: PeriodPreview[] = [];
    let cursor = start;
    const total = Math.max(1, count);

    for (let number = 1; number <= total; number++) {
        if (compare(cursor, end) > 0) break;

        let periodEnd =
            number === total ? end : addDays(addMonths(start, number), -1);

        if (compare(periodEnd, end) > 0) periodEnd = end;
        if (compare(periodEnd, cursor) < 0) periodEnd = cursor;

        periods.push({
            number,
            name: labelFor(cursor),
            start_date: toKey(cursor),
            end_date: toKey(periodEnd),
        });

        cursor = addDays(periodEnd, 1);
    }

    return periods;
}

export function monthsBetween(startDate: string, endDate: string): number {
    const start = parseDateKey(startDate);
    const end = parseDateKey(endDate);

    if (!start || !end) return 0;

    const months = (end.year - start.year) * 12 + (end.month - start.month);

    return end.day >= start.day ? months + 1 : months;
}
