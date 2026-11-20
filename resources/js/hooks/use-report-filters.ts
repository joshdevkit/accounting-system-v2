import { router } from '@inertiajs/react';
import { useMemo } from 'react';
import { compactQuery } from '@/lib/utils';
import type { ReportFilters, ReportFiscalYear, ReportPickers } from '@/types/reports';

/**
 * Shared filter-apply logic for report pages (Trial Balance, Income
 * Statement, ...). Keeps the "pick a fiscal year → auto-fill from/to" and
 * "pick a period → auto-fill from/to" behavior in one place so every report
 * page behaves identically.
 */
export function useReportFilters(routeName: string, filters: ReportFilters, pickers: ReportPickers) {
    const selectedYear = useMemo(
        () => pickers.fiscalYears.find((year) => year.id === filters.fiscal_year_id),
        [pickers.fiscalYears, filters.fiscal_year_id],
    );

    const apply = (next: Record<string, unknown>) => {
        router.get(routeName, compactQuery({ ...filters, ...next }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const selectFiscalYear = (value: string) => {
        const year = value ? Number(value) : null;
        const match: ReportFiscalYear | undefined = year
            ? pickers.fiscalYears.find((candidate) => candidate.id === year)
            : undefined;

        apply({
            fiscal_year_id: year,
            accounting_period_id: null,
            from: match?.start_date ?? null,
            to: match?.end_date ?? null,
        });
    };

    const selectPeriod = (value: string) => {
        const periodId = value ? Number(value) : null;
        const period = selectedYear?.periods.find((candidate) => candidate.id === periodId);

        apply({
            accounting_period_id: periodId,
            from: period?.start_date ?? filters.from,
            to: period?.end_date ?? filters.to,
        });
    };

    const reset = () => {
        router.get(routeName, {}, { preserveScroll: true });
    };

    return { selectedYear, apply, selectFiscalYear, selectPeriod, reset };
}