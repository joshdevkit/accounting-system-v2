import { ContactOption } from "./purchases/form";

export type Props = {
    purchase: {
        id: number;
        purchase_date: string;
        reference_no: string | null;
        vendor_name: string | null;
        contact: ContactOption | null;
        taxable_amount_cents: number;
        vat_amount_cents: number;
        withholding_tax_rate: number;
        withholding_tax_amount_cents: number;
        gross_amount_cents: number;
        net_payable_cents: number;

        tax_type: string;
        payment_status: "unpaid" | "paid";
        is_affiliate_settlement: boolean;
        settlement_account: string | null;
        notes: string | null;
        status: string;

        branch: string | null;

        account: {
            code: string;
            name: string;
        } | null;

        created_by: string | null;
        posted_by: string | null;
        posted_at: string | null;
        paid_by: string | null;
        paid_at: string | null;

        lines: {
            id: number;
            particulars: string;
            quantity: number;
            uom: string;
            unit_price: number;
            amount_cents: number;
            account: {
                code: string;
                name: string;
            } | null;
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

    draftJournalLines: {
        account_code: string | null;
        account_name: string | null;
        debit_cents: number;
        credit_cents: number;
    }[] | null;
    payableAccountOptions: { id: number; code: string; name: string; label: string }[];
    selectedPayableAccountId: number | null;
    affiliateAccountCode: string | null;

    paymentJournalEntry: {
        id: number;
        reference_no: string;
        is_allocation: boolean;
        lines: {
            account_code: string | null;
            account_name: string | null;
            debit_cents: number;
            credit_cents: number;
        }[];
    } | null;

    clearingJournalEntry: {
        id: number;
        reference_no: string;
        is_allocation: boolean;
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
        can_mark_for_voucher: boolean;
    };

    settlementOptions: {
        value: string;
        label: string;
    }[];

    currency: string;
};

export const TAX_TYPE_LABELS: Record<string, string> = {
    vatable: "VATable",
    non_vat: "Non-VAT",
    vat_exempt: "VAT-exempt",
};
