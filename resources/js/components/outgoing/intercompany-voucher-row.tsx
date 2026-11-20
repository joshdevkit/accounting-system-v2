import { Link } from "@inertiajs/react";

import { Checkbox } from "@/components/ui/checkbox";
import { TableCell, TableRow } from "@/components/ui/table";
import { StatusCombobox } from "@/components/outgoing/status-combobox";
import { formatCents } from "@/lib/format";
import type { IntercompanyVoucherRow as VoucherType } from "./types";

export function IntercompanyVoucherRow({ voucher, checked, onCheckedChange, currency }: {
    voucher: VoucherType;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    currency: string;
}) {
    const sourcePath = voucher.source_type === "purchase" ? "purchases" : "expenses";
    const sourceLabel = voucher.source_type === "purchase" ? "Purchase" : "Expense";
    // Only vouchers in "For Voucher" status can be selected for batch payment
    // (same as purchases — only untouched ones are selectable)
    const canCheck = voucher.processing_status === "for_voucher";

    return (
        <TableRow>
            <TableCell>
                <Checkbox label="" aria-label={`Select ${voucher.payee}`} checked={checked} onCheckedChange={onCheckedChange} disabled={!canCheck} />
            </TableCell>
            <TableCell>
                <Link href={`/${sourcePath}/${voucher.source_id}`} className="font-medium hover:underline">
                    {voucher.payee}
                </Link>
                <div className="text-muted-foreground text-xs">{sourceLabel} · Intercompany</div>
            </TableCell>
            <TableCell>
                <div>{voucher.source_reference ?? `#${voucher.source_id}`}</div>
                <div className="text-muted-foreground text-xs">{voucher.journal_reference}</div>
            </TableCell>
            <TableCell>—</TableCell>
            <TableCell>{voucher.branch ?? "Company-wide"}</TableCell>
            <TableCell className="text-muted-foreground font-mono text-xs">{voucher.clearing_account}</TableCell>
            <TableCell className="text-xs">
                {voucher.check_number
                    ? `${voucher.bank_name ?? ""} ${voucher.check_number}`.trim()
                    : "—"}
            </TableCell>
            <TableCell className="text-right tabular-nums">{formatCents(voucher.amount_cents, currency)}</TableCell>
            <TableCell>
                <StatusCombobox
                    value={voucher.processing_status}
                    onChange={(next) => window.dispatchEvent(new CustomEvent("voucher-status-change", { detail: { id: voucher.id, next } }))}
                />
            </TableCell>
        </TableRow>
    );
}
