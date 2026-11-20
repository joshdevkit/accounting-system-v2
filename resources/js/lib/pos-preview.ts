import type { PosPostingMappingRow } from '@/types/models';

export type PreviewLine = {
    label: string;
    code: string | null;
    name: string | null;
    side: 'debit' | 'credit' | 'either';
    debit: number;
    credit: number;
};

export type PostingPreview = {
    lines: PreviewLine[];
    debits: number;
    credits: number;
    variance: number;
    missingCodes: string[];
    empty: boolean;
};

const cents = (value: number | string | null | undefined): number => {
    const parsed = typeof value === 'number' ? value : Number(value ?? 0);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
};

/**
 * Mirrors PosPostingService::lines() so the form can show exactly which journal
 * lines a day summary will produce before it is posted.
 */
export function buildPostingPreview(
    amounts: Record<string, number | string | null>,
    mapping: PosPostingMappingRow[],
): PostingPreview {
    const lines: PreviewLine[] = [];
    let debits = 0;
    let credits = 0;

    for (const row of mapping) {
        if (row.field === 'variance') continue;

        const value = row.field === 'net_sales'
            ? cents(amounts.taxable_amount) + cents(amounts.exempted_amount)
            : cents(amounts[row.field]);

        if (value === 0) continue;

        const debit = row.side === 'debit' ? value : 0;
        const credit = row.side === 'credit' ? value : 0;

        lines.push({
            label: row.label,
            code: row.code,
            name: row.name,
            side: row.side,
            debit,
            credit,
        });

        debits += debit;
        credits += credit;
    }

    const variance = debits - credits;

    if (variance !== 0) {
        const row = mapping.find((candidate) => candidate.field === 'variance');

        // A shortage needs an extra debit, an overage an extra credit — the
        // mirror of the over/short line the service writes.
        lines.push({
            label: row?.label ?? 'Cash over / short',
            code: row?.code ?? null,
            name: row?.name ?? null,
            side: 'either',
            debit: Math.max(-variance, 0),
            credit: Math.max(variance, 0),
        });

        debits += Math.max(-variance, 0);
        credits += Math.max(variance, 0);
    }

    return {
        lines,
        debits,
        credits,
        variance: debits - credits,
        missingCodes: [
            ...new Set(
                lines.filter((line) => !line.code).map((line) => line.label),
            ),
        ],
        empty: lines.length === 0,
    };
}
