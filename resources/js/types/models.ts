export type Company = {
    id: number;
    code: string;
    name: string;
    tax_identification_number: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    base_currency_code: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type Branch = {
    id: number;
    company_id: number;
    code: string;
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * Branch as the list and picker endpoints return it (a subset of columns).
 */
export type BranchSummary = Pick<Branch, "id" | "name" | "code"> & {
    is_active?: boolean;
};

export type BranchRow = Branch & {
    journal_entries: number;
    pos_entries: number;
};

export type AccountCategory =
    | "asset"
    | "liability"
    | "equity"
    | "revenue"
    | "expense";

export type AccountType = {
    id: number;
    code: string;
    name: string;
    category: AccountCategory;
    normal_balance: "debit" | "credit";
    description?: string | null;
};

export type AccountRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    depth: number;
    is_control_account: boolean;
    is_active: boolean;
    children: number;
    lines: number;
    is_postable: boolean;
    parent: { id: number; code: string; name: string } | null;
    account_type: AccountType | null;
    debit_cents: number;
    credit_cents: number;
    balance_cents: number;
};

/**
 * A leaf account offered in a journal entry line picker.
 */
export type AccountOption = {
    id: number;
    code: string;
    name: string;
    label: string;
    category: AccountCategory | null;
    normal_balance: "debit" | "credit" | null;
    group: string | null;
    is_active: boolean;
    is_summary: boolean;
};

export type ParentOption = {
    id: number;
    code: string;
    name: string;
    label: string;
};

export type PeriodRow = {
    id: number;
    period_number: number;
    name: string;
    start_date: string;
    end_date: string;
    status: "open" | "closed";
    entries: number;
    drafts?: number;
};

export type FiscalYearRow = {
    id: number;
    year: number;
    status: "open" | "closed";
    start_date: string;
    end_date: string;
    entries: number;
    period_count: number | null;
    periods: PeriodRow[];
};

export type PeriodInfo = {
    id: number;
    name: string;
    number: number;
    status: "open" | "closed";
    start_date: string;
    end_date: string;
    allows_posting: boolean;
    fiscal_year: { year: number; status: "open" | "closed" } | null;
};

export type JournalEntryRow = {
    id: number;
    reference_no: string;
    transaction_date: string;
    description: string;
    status: "draft" | "posted" | "reversed";
    source_type: "manual" | "pos";
    line_count: number;
    debit_cents: number;
    credit_cents: number;
    is_balanced: boolean;
    branch: string | null;
    period: { id: number; name: string; status: "open" | "closed" } | null;
    pos_reference: string | null;
};

export type JournalEntryLineRow = {
    id: number;
    account_id: number | null;
    account_code: string | null;
    account_name: string | null;
    account_category: AccountCategory | null;
    account_inactive: boolean;
    debit_cents: number;
    credit_cents: number;
    description: string | null;
};

export type JournalEntryDetail = {
    id: number;
    reference_no: string;
    transaction_date: string;
    description: string;
    status: "draft" | "posted" | "reversed";

    source_type: "manual" | "pos" | "purchase";
    source_reference: string | null;

    created_at: string | null;
    posted_at: string | null;
    created_by: string | null;
    posted_by: string | null;

    branch: {
        id: number;
        name: string;
    } | null;

    contact: {
        id: number;
        name: string;
        tax_identification_number: string | null;
        address: string | null;
    } | null;

    period: {
        id: number;
        name: string;
        status: "open" | "closed";
        allows_posting: boolean;
    } | null;

    fiscal_year: {
        year: number;
        status: "open" | "closed";
    } | null;

    pos_reference: string | null;
    pos_entry_id: number | null;

    reverses: string | null;

    reversal: {
        id: number;
        reference_no: string;
        transaction_date: string;
    } | null;

    purchase: {
        id: number;
        purchase_date: string;
        reference_no: string | null;
        vendor_name: string | null;
        contact: {
            id: number;
            name: string;
        } | null;
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
    } | null;

    lines: JournalEntryLineRow[];
};

export type JournalLineInput = {
    account_id: number | "";
    debit: number | string;
    credit: number | string;
    description: string;
};

export type PosAmounts = {
    gross_sales: number;
    discount_amount: number;
    taxable_amount: number;
    exempted_amount: number;
    vat_amount: number;
    local_tax_amount: number;
    service_charge_amount: number;
    net_sales: number;
    cash_amount: number;
    card_amount: number;
    gcash_qrph_amount: number;
    bank_transfer_amount: number;
    cheque_amount: number;
    accounts_receivable_amount: number;
    room_post_amount: number;
    gift_voucher_amount: number;
    other_payment_amount: number;
};

export type PaymentField = keyof Pick<
    PosAmounts,
    | "cash_amount"
    | "card_amount"
    | "gcash_qrph_amount"
    | "bank_transfer_amount"
    | "cheque_amount"
    | "accounts_receivable_amount"
    | "room_post_amount"
    | "gift_voucher_amount"
    | "other_payment_amount"
>;

export type PosEntryRow = {
    id: number;
    business_date: string;
    pos_reference: string;
    status: "draft" | "posted" | "voided";
    branch: { id: number; name: string; code: string } | null;
    journal_entry: {
        id: number;
        reference_no: string;
        status: "draft" | "posted" | "reversed";
    } | null;
    created_by: string | null;
    amounts: Pick<
        PosAmounts,
        | "gross_sales"
        | "discount_amount"
        | "taxable_amount"
        | "exempted_amount"
        | "vat_amount"
        | "local_tax_amount"
        | "service_charge_amount"
        | "net_sales"
    >;
    tendered_cents: number;
    variance_cents: number;
};

export type PosPostingMappingRow = {
    field: string;
    label: string;
    side: "debit" | "credit" | "either";
    code: string | null;
    name: string | null;
};

export type PosEntryDetail = {
    sales_details: Record<string, string | null>;
    id: number;
    business_date: string;
    pos_reference: string;
    status: "draft" | "posted" | "voided";
    notes: string | null;
    created_at: string | null;
    posted_at: string | null;
    voided_at: string | null;
    created_by: string | null;
    posted_by: string | null;
    voided_by: string | null;
    branch: { id: number; name: string; code: string } | null;
    amounts: Record<keyof PosAmounts, number>;
    tenders: { field: string; label: string; cents: number }[];
    tendered_cents: number;
    expected_cents: number;
    variance_cents: number;
};

export type LinkedJournalEntry = {
    id: number;
    reference_no: string;
    status: "draft" | "posted" | "reversed";
    period: string | null;
    branch: string | null;
    lines: {
        id: number;
        account_code: string | null;
        account_name: string | null;
        debit_cents: number;
        credit_cents: number;
        description: string | null;
    }[];
    totals: { debits: number; credits: number };
};

export type AuditLogRow = {
    id: number;
    action: string;
    created_at: string | null;
    subject: string;
    auditable_type: string | null;
    auditable_id: number | null;
    user: { name: string; email: string } | null;
    ip_address: string | null;
    changes: {
        field: string;
        old: string | number | boolean | null;
        new: string | number | boolean | null;
    }[];
};

export type PaginationLink = {
    label: string;
    url: string | null;
    active: boolean;
};

export type FilterOption = {
    value: string;
    label: string;
};
