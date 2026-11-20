export type ProcessingStatus =
  | "pending"
  | "for_cheque"
  | "for_release"
  | "for_voucher"
  | "released"
  | "cancelled";

export type PaymentConfirmationAction = "cancel_payment" | "void_purchase";

export interface OutgoingEntry {
  id: number;
  purchase_date: string;
  due_date: string | null;
  vendor_name: string | null;
  reference_no: string | null;
  net_payable_cents: number;
  branch: string | null;
  payable_account: string | null;
  clearing_account: string | null;
  settlement_accounts: Record<string, string | null>;
  bank_name: string | null;
  check_number: string | null;
  settlement_account: string | null;
  processing_status: ProcessingStatus;
}

export interface SettlementOption {
  value: string;
  label: string;
}

export interface IntercompanyVoucherRow {
  id: number;
  source_type: "purchase" | "expense";
  source_id: number;
  source_reference: string | null;
  journal_reference: string | null;
  payee: string;
  branch: string | null;
  clearing_account: string;
  settlement_accounts: Record<string, string | null>;
  bank_name: string | null;
  check_number: string | null;
  settlement_account: string | null;
  processing_status: ProcessingStatus;
  amount_cents: number;
}

export interface OutgoingPaymentsProps {
  entries: OutgoingEntry[];
  intercompanyVouchers: IntercompanyVoucherRow[];
  settlementOptions: SettlementOption[];
  currency: string;
}
