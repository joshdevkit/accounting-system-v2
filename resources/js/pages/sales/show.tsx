import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, Ban, Pencil, Send, Trash2 } from "lucide-react";
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
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCents, formatDate, formatDateTime } from "@/lib/format";
import { Props, TAX_TYPE_LABELS } from "@/types/sales";
import { JournalTable, Row } from "@/components/sales/sales-table";
import sales from "@/routes/sales";
import { DeleteConfirmDialog } from "@/components/delete-dialog/remove";

export default function ShowSale({
    sale,
    journalEntry,
    paymentJournalEntry,
    actions,
    settlementOptions,
    currency,
}: Props) {
    const post = () => router.post(`/sales/${sale.id}/post`, {});

    const voidSale = () => {
        if (!window.confirm("Void this sale?")) return;
        router.post(`/sales/${sale.id}/void`, {});
    };

    const remove = () => {
        router.delete(sales.destroy(sale.id), {
            onSuccess: () => router.get("/sales"),
        });
    };

    const receiveForm = useForm({ settlement_account: "" });
    const [showReceiveForm, setShowReceiveForm] = useState(false);

    const submitReceive = (event: React.FormEvent) => {
        event.preventDefault();
        receiveForm.post(`/sales/${sale.id}/receive`, {
            onSuccess: () => setShowReceiveForm(false),
        });
    };

    return (
        <AuthenticatedLayout
            title={sale.customer_name ?? `Sale #${sale.id}`}
            description={formatDate(sale.sale_date)}
            actions={
                <>
                    {actions.can_edit && (
                        <Link href={`/sales/${sale.id}/edit`}>
                            <Button size="sm" variant="outline">
                                <Pencil className="size-4" />
                                Edit
                            </Button>
                        </Link>
                    )}
                    {actions.can_post && (
                        <Button size="sm" onClick={post}>
                            <Send className="size-4" />
                            Post to ledger
                        </Button>
                    )}
                    {actions.can_void && (
                        <Button size="sm" variant="outline" onClick={voidSale}>
                            <Ban className="size-4" />
                            Void
                        </Button>
                    )}
                    {actions.can_delete && (
                        <DeleteConfirmDialog
                            title="Delete this sale?"
                            description={`This action cannot be undone. The sale${
                                sale.customer_name
                                    ? ` for ${sale.customer_name}`
                                    : "Anonymous"
                            } will be permanently deleted.`}
                            confirmText="Delete Sales"
                            onConfirm={remove}
                        />
                    )}
                </>
            }
        >
            <Head title={`Sale · ${sale.customer_name ?? sale.id}`} />

            <div className="space-y-4">
                <Link
                    href="/sales"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to sales
                </Link>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                        <CardDescription>
                            <StatusBadge value={sale.status} />
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-1.5 text-sm">
                        <Row
                            label="Customer"
                            value={sale.customer_name ?? "—"}
                        />
                        <Row
                            label="Revenue account"
                            value={
                                sale.account
                                    ? `${sale.account.code} · ${sale.account.name}`
                                    : "—"
                            }
                        />
                        <Row
                            label="Branch"
                            value={sale.branch ?? "Company-wide"}
                        />
                        <Row
                            label="Reference"
                            value={sale.reference_no ?? "—"}
                        />
                        <Row
                            label="Tax type"
                            value={
                                TAX_TYPE_LABELS[sale.tax_type] ?? sale.tax_type
                            }
                        />
                        <Row
                            label="Taxable amount"
                            value={formatCents(
                                sale.taxable_amount_cents,
                                currency,
                            )}
                        />
                        {sale.vat_amount_cents > 0 && (
                            <Row
                                label="VAT"
                                value={formatCents(
                                    sale.vat_amount_cents,
                                    currency,
                                )}
                            />
                        )}
                        <Row
                            label="Gross amount"
                            value={formatCents(
                                sale.gross_amount_cents,
                                currency,
                            )}
                        />
                        {sale.withholding_tax_amount_cents > 0 && (
                            <Row
                                label={`Withholding tax (${sale.withholding_tax_rate}%)`}
                                value={`(${formatCents(sale.withholding_tax_amount_cents, currency)})`}
                            />
                        )}
                        <div className="flex items-center justify-between gap-2 border-t pt-2 font-medium">
                            <span>Net receivable</span>
                            <span className="tabular-nums">
                                {formatCents(
                                    sale.net_receivable_cents,
                                    currency,
                                )}
                            </span>
                        </div>
                        <Row
                            label="Payment"
                            value={
                                sale.payment_status === "paid"
                                    ? "Received"
                                    : "Outstanding"
                            }
                        />
                        {sale.notes && <Row label="Notes" value={sale.notes} />}
                    </CardContent>
                </Card>

                {actions.can_mark_paid && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment</CardTitle>
                            <CardDescription>
                                This sale is still outstanding
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!showReceiveForm ? (
                                <Button
                                    size="sm"
                                    onClick={() => setShowReceiveForm(true)}
                                >
                                    Receive payment
                                </Button>
                            ) : (
                                <form
                                    onSubmit={submitReceive}
                                    className="flex flex-wrap items-end gap-3"
                                >
                                    <div className="space-y-1">
                                        <label
                                            htmlFor="settlement_account"
                                            className="text-muted-foreground text-xs"
                                        >
                                            Received into
                                        </label>
                                        <Select
                                            id="settlement_account"
                                            value={
                                                receiveForm.data
                                                    .settlement_account
                                            }
                                            onChange={(event) =>
                                                receiveForm.setData(
                                                    "settlement_account",
                                                    event.target.value,
                                                )
                                            }
                                            className="w-auto min-w-40"
                                        >
                                            <option value="">
                                                Choose a destination…
                                            </option>
                                            {settlementOptions.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </Select>
                                        {receiveForm.errors
                                            .settlement_account && (
                                            <p className="text-destructive text-xs">
                                                {
                                                    receiveForm.errors
                                                        .settlement_account
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={receiveForm.processing}
                                    >
                                        Confirm payment
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            setShowReceiveForm(false)
                                        }
                                    >
                                        Cancel
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                )}

                {sale.payment_status === "paid" && sale.paid_at && (
                    <p className="text-muted-foreground text-xs">
                        Received {formatDateTime(sale.paid_at)}
                        {sale.paid_by ? ` by ${sale.paid_by}` : ""}
                    </p>
                )}

                {journalEntry && (
                    <JournalTable
                        title={`Journal entry ${journalEntry.reference_no}`}
                        lines={journalEntry.lines}
                        currency={currency}
                    />
                )}

                {paymentJournalEntry && (
                    <JournalTable
                        title={`Payment journal entry ${paymentJournalEntry.reference_no}`}
                        lines={paymentJournalEntry.lines}
                        currency={currency}
                    />
                )}

                <p className="text-muted-foreground text-xs">
                    Recorded {sale.created_by ? `by ${sale.created_by}` : ""}
                    {sale.posted_at &&
                        ` · Posted ${formatDateTime(sale.posted_at)}${sale.posted_by ? ` by ${sale.posted_by}` : ""}`}
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
