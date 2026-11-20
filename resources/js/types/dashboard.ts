export type Kpi = {
    key: string;
    label: string;
    cents?: number;
    count?: number;
    hint: string;
};

export type Props = {
    company: {
        name: string;
        code: string;
        currency: string;
        is_active: boolean;
    };
    businessDate: string;
    branchId: number | null;
    branches: { id: number; name: string; code: string; is_active: boolean }[];
    fiscalYear: {
        id: number;
        year: number;
        status: string;
        start_date: string;
        end_date: string;
    } | null;
    period: {
        id: number;
        name: string;
        number: number;
        status: string;
        allows_posting: boolean;
    } | null;
    kpis: Kpi[];
    tenders: { field: string; label: string; cents: number }[];
    posSummaries: { posted: number; drafts: number };

    /** chart data  */
    cashFlow: { month: string; cash_in: number; cash_out: number }[];
    incomeExpense: { month: string; income: number; expense: number }[];
    receivables: { total: number; current: number; overdue: number };
    payables: { total: number; current: number; overdue: number };
    /** end of chart data */

    recentEntries: {
        id: number;
        reference_no: string | null;
        transaction_date: string | null;
        description: string | null;
        status: string;
        source_type: string;
        branch: string | null;
        period: string | null;
        line_count: number;
        cents: number;
    }[];
    alerts: {
        level: string;
        message: string;
        href?: string;
        action?: string;
    }[];
};
