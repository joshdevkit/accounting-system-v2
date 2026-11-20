export type ReportBranch = { id: number; name: string; code: string };

export type ReportPeriod = {
    id: number;
    name: string;
    period_number: number;
    start_date: string;
    end_date: string;
    status: string;
};

export type ReportFiscalYear = {
    id: number;
    year: number;
    status: string;
    start_date: string;
    end_date: string;
    periods: ReportPeriod[];
};

export type ReportPickers = {
    branches: ReportBranch[];
    fiscalYears: ReportFiscalYear[];
};

export type ReportFilters = Record<string, unknown> & {
    branch_id?: number | null;
    fiscal_year_id?: number | null;
    accounting_period_id?: number | null;
    from?: string | null;
    to?: string | null;
};

export type ReportCompany = {
    name: string;
    code: string;
    currency: string;
};