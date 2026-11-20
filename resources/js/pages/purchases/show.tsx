import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowLeft,
    Ban,
    Check,
    ChevronsUpDown,
    Download,
    FileText,
    Pencil,
    Send,
    Trash2,
} from "lucide-react";
import { useState } from "react";
import { useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCents, formatDate, formatDateTime } from "@/lib/format";
import { Props, TAX_TYPE_LABELS } from "@/types/purchases";
import purchases from "@/routes/purchases";
import { DeleteConfirmDialog } from "@/components/delete-dialog/remove";
import { Input } from "@/components/ui/input";
import { AccountCombobox } from "@/components/journal/account-combobox";
import { LinkedIntercompanyEntry } from "@/components/journal/linked-intercompany-entry";

export default function ShowPurchase({
    purchase,
    journalEntry,
    draftJournalLines,
    payableAccountOptions,
    selectedPayableAccountId,
    affiliateAccountCode,
    paymentJournalEntry,
    clearingJournalEntry,
    intercompanyJournalEntry,
    intercompanyVoucher,
    intercompanyAccounts,
    intercompanyBranches,
    actions,
    settlementOptions,
    currency,
}: Props) {
    const postForm = useForm<{ payable_account_id: number | "" }>({
        payable_account_id: selectedPayableAccountId ?? "",
    });
    const post = () => postForm.post(`/purchases/${purchase.id}/post`);
    const selectedPayable = payableAccountOptions.find(
        (account) => account.id === postForm.data.payable_account_id,
    );
    const previewLines = draftJournalLines?.map((line, index) => {
        if (index !== draftJournalLines.length - 1 || !selectedPayable) return line;
        return {
            ...line,
            account_code: selectedPayable.code,
            account_name: selectedPayable.name,
        };
    });

    const voidPurchase = () => {
        if (!window.confirm("Void this purchase?")) return;
        router.post(`/purchases/${purchase.id}/void`, {});
    };

    const markForVoucher = () => {
        router.patch(`/purchases/${purchase.id}/processing-status`, {
            processing_status: "for_check",
        });
    };

    const remove = () => {
        router.delete(purchases.destroy(purchase.id), {
            onSuccess: () => router.get("/purchases"),
        });
    };

    const payForm = useForm({
        settlement_account: "",
        bank_name: "",
        check_number: "",
    });
    const [showPayForm, setShowPayForm] = useState(false);

    const submitPayment = (event: React.FormEvent) => {
        event.preventDefault();
        payForm.post(`/purchases/${purchase.id}/pay`, {
            onSuccess: () => setShowPayForm(false),
        });
    };

    const goBack = (event: React.MouseEvent<HTMLAnchorElement>) => {
        // Let ctrl/cmd/shift-click and middle-click open a new tab as usual.
        if (
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.button !== 0
        ) {
            return;
        }

        event.preventDefault();

        if (window.history.length > 1) {
            window.history.back();
        } else {
            router.get("/purchases");
        }
    };

    return (
        <AuthenticatedLayout
            title={purchase.vendor_name ?? `Purchase #${purchase.id}`}
            description={formatDate(purchase.purchase_date)}
            actions={
                <>
                    {actions.can_edit && (
                        <Link href={`/purchases/${purchase.id}/edit`}>
                            <Button size="sm" variant="outline">
                                <Pencil className="size-4" />
                                Edit
                            </Button>
                        </Link>
                    )}
                    {actions.can_post && (
                        <Button size="sm" onClick={post} disabled={postForm.processing || !postForm.data.payable_account_id}>
                            <Send className="size-4" />
                            Post to ledger
                        </Button>
                    )}

                    {purchase.status === "posted" && purchase.payment_status === "unpaid" && !intercompanyJournalEntry && (
                        <a href="#intercompany-entry">
                            <Button size="sm" variant="outline" type="button">
                                <FileText className="size-4" />
                                Add intercompany JE
                            </Button>
                        </a>
                    )}

                    {actions.can_mark_for_voucher && (
                        <Button
                            size="sm"
                            variant="default"
                            onClick={markForVoucher}
                        >
                            <FileText className="size-4" />
                            For Voucher
                        </Button>
                    )}

                    {actions.can_void && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={voidPurchase}
                        >
                            <Ban className="size-4" />
                            Void
                        </Button>
                    )}
                    {actions.can_delete && (
                        <DeleteConfirmDialog
                            title="Delete this purchase?"
                            description={`This will permanently delete ${purchase.vendor_name ?? "Anonymous"} and cannot be undone.`}
                            confirmText="Delete Purchases"
                            onConfirm={remove}
                        />
                    )}
                </>
            }
        >
            <Head title={`Purchase · ${purchase.vendor_name ?? purchase.id}`} />

            <div className="space-y-4">
                <a
                    href="/purchases"
                    onClick={goBack}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back
                </a>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                        <CardDescription>
                            <StatusBadge value={purchase.status} />
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-1.5 text-sm">
                        <Row
                            label="Vendor"
                            value={purchase.vendor_name ?? "—"}
                        />
                        <Row
                            label="Branch"
                            value={purchase.branch ?? "Company-wide"}
                        />
                        <Row
                            label="Reference"
                            value={purchase.reference_no ?? "—"}
                        />
                        <Row
                            label="Tax type"
                            value={
                                TAX_TYPE_LABELS[purchase.tax_type] ??
                                purchase.tax_type
                            }
                        />
                        <Row
                            label="Taxable amount"
                            value={formatCents(
                                purchase.taxable_amount_cents,
                                currency,
                            )}
                        />
                        {purchase.vat_amount_cents > 0 && (
                            <Row
                                label="VAT"
                                value={formatCents(
                                    purchase.vat_amount_cents,
                                    currency,
                                )}
                            />
                        )}
                        <Row
                            label="Gross amount"
                            value={formatCents(
                                purchase.gross_amount_cents,
                                currency,
                            )}
                        />
                        {purchase.withholding_tax_amount_cents > 0 && (
                            <Row
                                label={`Withholding tax (${purchase.withholding_tax_rate}%)`}
                                value={`(${formatCents(purchase.withholding_tax_amount_cents, currency)})`}
                            />
                        )}
                        <div className="flex items-center justify-between gap-2 border-t pt-2 font-medium">
                            <span>Net payable</span>
                            <span className="tabular-nums">
                                {formatCents(
                                    purchase.net_payable_cents,
                                    currency,
                                )}
                            </span>
                        </div>
                        <Row
                            label="Payment"
                            value={
                                purchase.payment_status === "paid"
                                    ? "Paid"
                                    : intercompanyVoucher?.status === "for_voucher"
                                    ? "Intercompany · For Voucher"
                                    : purchase.is_affiliate_settlement
                                    ? "Affiliate settlement · ledger only"
                                    : "Unpaid"
                            }
                        />
                        {purchase.notes && (
                            <Row label="Notes" value={purchase.notes} />
                        )}
                    </CardContent>
                </Card>

                {purchase.payment_status === "paid" && purchase.paid_at && (
                    <p className="text-muted-foreground text-xs">
                        Paid {formatDateTime(purchase.paid_at)}
                        {purchase.paid_by ? ` by ${purchase.paid_by}` : ""}
                    </p>
                )}

                {journalEntry && (
                    <JournalTable
                        title={`Journal entry ${journalEntry.reference_no}`}
                        lines={journalEntry.lines}
                        currency={currency}
                        purchaseLines={purchase.lines}
                    />
                )}

                {purchase.status === "posted" && (purchase.payment_status === "unpaid" || intercompanyJournalEntry) && (
                    <LinkedIntercompanyEntry
                        source="purchases"
                        sourceId={purchase.id}
                        date={purchase.purchase_date}
                        currency={currency}
                        entry={intercompanyJournalEntry}
                        voucher={intercompanyVoucher}
                        canMarkForVoucher={purchase.payment_status === "unpaid"}
                        accounts={intercompanyAccounts}
                        branches={intercompanyBranches}
                    />
                )}

                {draftJournalLines && (
                    <JournalTable
                        title="Draft journal preview"
                        lines={previewLines ?? []}
                        currency={currency}
                        purchaseLines={purchase.lines}
                        footer={
                            <div className="space-y-2 border-t px-4 py-4">
                                <label className="text-sm font-medium">Credit account</label>
                                <div className="max-w-md">
                                    <AccountCombobox
                                        groups={[{ category: "Liability accounts", options: payableAccountOptions }]}
                                        value={postForm.data.payable_account_id}
                                        onChange={(value) => postForm.setData("payable_account_id", value)}
                                        placeholder="Select an account before posting"
                                        aria-label="Credit account"
                                    />
                                </div>
                                {selectedPayable?.code === affiliateAccountCode && (
                                    <p className="text-muted-foreground text-sm">
                                        This posts as Due to Affiliates. It stays in the ledger and will not enter Outgoing Payments.
                                    </p>
                                )}
                                {postForm.errors.payable_account_id && (
                                    <p className="text-destructive text-sm">{postForm.errors.payable_account_id}</p>
                                )}
                                <Button onClick={post} disabled={postForm.processing || !postForm.data.payable_account_id}>
                                    <Send className="size-4" />
                                    Post to ledger
                                </Button>
                            </div>
                        }
                    />
                )}

                {paymentJournalEntry && (
                    <JournalTable
                        title={paymentJournalEntry.is_allocation
                            ? `Payment allocation from batch journal ${paymentJournalEntry.reference_no}`
                            : `Payment journal entry ${paymentJournalEntry.reference_no}`}
                        lines={paymentJournalEntry.lines}
                        currency={currency}
                        batchJournalId={paymentJournalEntry.is_allocation ? paymentJournalEntry.id : undefined}
                    />
                )}

                {clearingJournalEntry && (
                    <JournalTable
                        title={clearingJournalEntry.is_allocation
                            ? `A/P clearing allocation from batch journal ${clearingJournalEntry.reference_no}`
                            : `A/P clearing journal entry ${clearingJournalEntry.reference_no}`}
                        lines={clearingJournalEntry.lines}
                        currency={currency}
                        batchJournalId={clearingJournalEntry.is_allocation ? clearingJournalEntry.id : undefined}
                    />
                )}

                <p className="text-muted-foreground text-xs">
                    Recorded{" "}
                    {purchase.created_by ? `by ${purchase.created_by}` : ""}
                    {purchase.posted_at &&
                        ` · Posted ${formatDateTime(purchase.posted_at)}${purchase.posted_by ? ` by ${purchase.posted_by}` : ""}`}
                </p>
            </div>
        </AuthenticatedLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-2 border-b pb-1.5 last:border-0 last:pb-0">
            <span className="text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}

function JournalTable({
    title,
    lines,
    currency,
    purchaseLines,
    batchJournalId,
    footer,
}: {
    title: string;
    lines: {
        account_code: string | null;
        account_name: string | null;
        debit_cents: number;
        credit_cents: number;
    }[];
    currency: string;
    batchJournalId?: number;
    footer?: React.ReactNode;
    purchaseLines?: {
        id?: number;
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
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {batchJournalId && (
                    <CardDescription>
                        Showing this purchase’s allocation.{" "}
                        <Link
                            href={`/journal-entries/${batchJournalId}`}
                            className="text-primary underline underline-offset-4"
                        >
                            View full batch journal
                        </Link>
                    </CardDescription>
                )}
            </CardHeader>

            <CardContent className="p-0">
                {/* Purchase line items */}
                {purchaseLines && purchaseLines.length > 0 && (
                    <div className="border-b">
                        <div className="bg-muted/20 border-y px-4 py-2">
                            <h4 className="text-sm font-medium">
                                Purchase Line Items
                            </h4>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs">
                                        <th className="px-4 py-2 font-medium">
                                            #
                                        </th>

                                        <th className="px-4 py-2 font-medium">
                                            Particulars
                                        </th>

                                        <th className="px-4 py-2 text-right font-medium">
                                            Qty
                                        </th>

                                        <th className="px-4 py-2 font-medium">
                                            UOM
                                        </th>

                                        <th className="px-4 py-2 text-right font-medium">
                                            Unit Price
                                        </th>

                                        <th className="px-4 py-2 text-right font-medium">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {purchaseLines.map((line, index) => (
                                        <tr
                                            key={line.id ?? index}
                                            className="border-b last:border-0"
                                        >
                                            <td className="text-muted-foreground px-4 py-2">
                                                {index + 1}
                                            </td>

                                            <td className="px-4 py-2">
                                                <div className="font-medium">
                                                    {line.particulars}
                                                </div>

                                                {line.account && (
                                                    <div className="text-muted-foreground font-mono text-xs">
                                                        {line.account.code} ·{" "}
                                                        {line.account.name}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {line.quantity.toLocaleString(
                                                    undefined,
                                                    {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2,
                                                    },
                                                )}
                                            </td>

                                            <td className="text-muted-foreground px-4 py-2">
                                                {line.uom}
                                            </td>

                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {formatCents(
                                                    Math.round(
                                                        line.unit_price * 100,
                                                    ),
                                                    currency,
                                                )}
                                            </td>

                                            <td className="px-4 py-2 text-right font-medium tabular-nums">
                                                {formatCents(
                                                    line.amount_cents,
                                                    currency,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>

                                <tfoot>
                                    <tr className="bg-muted/20 border-t">
                                        <td
                                            colSpan={5}
                                            className="px-4 py-2 text-right font-medium"
                                        >
                                            Total
                                        </td>

                                        <td className="px-4 py-2 text-right font-semibold tabular-nums">
                                            {formatCents(
                                                purchaseLines.reduce(
                                                    (total, line) =>
                                                        total +
                                                        line.amount_cents,
                                                    0,
                                                ),
                                                currency,
                                            )}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                )}

                {/* Journal summary */}
                <div>
                    <div className="bg-muted/20 border-b px-4 py-2">
                        <h4 className="text-sm font-medium">Journal Summary</h4>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">
                                        Account
                                    </th>

                                    <th className="px-4 py-2 text-right font-medium">
                                        Debit
                                    </th>

                                    <th className="px-4 py-2 text-right font-medium">
                                        Credit
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {lines.map((line, index) => (
                                    <tr
                                        key={index}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-4 py-2">
                                            <span className="text-muted-foreground font-mono text-xs">
                                                {line.account_code}
                                            </span>{" "}
                                            {line.account_name}
                                        </td>

                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {line.debit_cents
                                                ? formatCents(
                                                      line.debit_cents,
                                                      currency,
                                                  )
                                                : "—"}
                                        </td>

                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {line.credit_cents
                                                ? formatCents(
                                                      line.credit_cents,
                                                      currency,
                                                  )
                                                : "—"}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
                {footer}
            </CardContent>
        </Card>
    );
}
