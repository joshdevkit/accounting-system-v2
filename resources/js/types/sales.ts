export type Props = {
    sale: {
        id: number;
        sale_date: string;
        reference_no: string | null;
        customer_name: string | null;
        taxable_amount_cents: number;
        vat_amount_cents: number;
        withholding_tax_rate: number;
        withholding_tax_amount_cents: number;
        gross_amount_cents: number;
        net_receivable_cents: number;
        tax_type: string;
        payment_status: 'unpaid' | 'paid';
        settlement_account: string | null;
        notes: string | null;
        status: string;
        branch: string | null;
        account: { code: string; name: string } | null;
        created_by: string | null;
        posted_by: string | null;
        posted_at: string | null;
        paid_by: string | null;
        paid_at: string | null;
    };
    journalEntry: {
        id: number;
        reference_no: string;
        lines: {
            account_code: string | null;
            account_name: string | null;
            debit_cents: number;
            credit_cents: number;
        }[];
    } | null;
    paymentJournalEntry: {
        id: number;
        reference_no: string;
        lines: {
            account_code: string | null;
            account_name: string | null;
            debit_cents: number;
            credit_cents: number;
        }[];
    } | null;
    actions: {
        can_edit: boolean;
        can_post: boolean;
        can_void: boolean;
        can_delete: boolean;
        can_mark_paid: boolean;
    };
    settlementOptions: { value: string; label: string }[];
    currency: string;
};

export const TAX_TYPE_LABELS: Record<string, string> = {
    vatable: 'VATable',
    non_vat: 'Non-VAT',
    vat_exempt: 'VAT-exempt',
};