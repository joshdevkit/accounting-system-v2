export type Props = {
    expense: {
        id: number;
        expense_date: string;
        reference_no: string | null;
        payee: string | null;
        net_payable_cents: number;
        payment_status: "paid" | "unpaid";
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
        lines: {
            particulars: string;
            quantity: number;
            uom: string | null;
            unit_price: number;
            amount_cents: number;
            account_code: string | null;
            account_name: string | null;
        }[];
    };
    journalEntry: {
        id: number;
        reference_no: string;
        status: string;
        lines: {
            account_code: string | null;
            account_name: string | null;
            debit_cents: number;
            credit_cents: number;
        }[];
    } | null;
    intercompanyJournalEntry: import("@/components/journal/linked-intercompany-entry").LinkedIntercompanyJournal | null;
    intercompanyVoucher: { id: number; status: string } | null;
    intercompanyAccounts: import("@/types/models").AccountOption[];
    intercompanyBranches: { id: number; name: string; code: string }[];
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
