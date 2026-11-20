import { PurchaseLineInput } from "@/components/purchases/purchase-lines-editor";

export type ContactOption = {
    id: number;
    name: string;
    tax_identification_number: string | null;
    address: string | null;
    default_payment_terms_days: number;
};

export type BranchOption = {
    id: number;
    name: string;
    code: string;
    account_suffix: string | null;
};

export type PurchaseFormData = {
    branch_id: string;
    contact_id: number | "";
    vendor_name: string;
    purchase_date: string;
    due_date: string;
    reference_no: string;
    description: string;
    withholding_tax_rate: string;
    notes: string;
    post_now: boolean;
    lines: PurchaseLineInput[];
};

export type PurchaseInitial = Partial<{
    branch_id: string | null;
    contact_id: number | null;
    vendor_name: string | null;
    debit_account_id?: number;
    taxable_amount?: number;
    tax_type?: string;
    purchase_date: string;
    due_date: string | null;
    reference_no: string | null;
    description: string | null;
    withholding_tax_rate: number | string | null;
    notes: string | null;
    lines: PurchaseLineInput[];
}>;