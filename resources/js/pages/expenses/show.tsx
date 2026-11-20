import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, Ban, Download, Pencil, Send, Trash2 } from "lucide-react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button } from "@/components/ui/button";
import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { Select } from "@/components/ui/select";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCents, formatDate, formatDateTime } from "@/lib/format";
import { Props } from "@/types/expenses";
import { DeleteConfirmDialog } from "@/components/delete-dialog/remove";
import { Input } from "@/components/ui/input";
import { LinkedIntercompanyEntry } from "@/components/journal/linked-intercompany-entry";

export default function ShowExpense({
    expense,
    journalEntry,
    paymentJournalEntry,
    intercompanyJournalEntry,
    intercompanyVoucher,
    intercompanyAccounts,
    intercompanyBranches,
    actions,
    settlementOptions,
    currency,
}: Props) {
    const post = () => router.post(`/expenses/${expense.id}/post`, {});

    const voidExpense = () => {
        if (!window.confirm(`Void this expense?`)) return;
        router.post(`/expenses/${expense.id}/void`, {});
    };

    const remove = () => {
        router.delete(`/expenses/${expense.id}`, {
            onSuccess: () => router.get("/expenses"),
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
        payForm.post(`/expenses/${expense.id}/pay`, {
            onSuccess: () => setShowPayForm(false),
        });
    };

    return (
        <AuthenticatedLayout
            title={expense.payee ?? `Expense #${expense.id}`}
            description={formatDate(expense.expense_date)}
            actions={
                <>
                    {expense.payment_status === "paid" &&
                        expense.settlement_account === "cheque" && (
                            <a href={`/expenses/${expense.id}/check-voucher`}>
                                <Button size="sm" variant="outline">
                                    <Download className="size-4" />
                                    Check Voucher
                                </Button>
                            </a>
                        )}
                    {actions.can_edit && (
                        <Link href={`/expenses/${expense.id}/edit`}>
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
                    {expense.status === "posted" && expense.payment_status === "unpaid" && !intercompanyJournalEntry && (
                        <a href="#intercompany-entry">
                            <Button size="sm" variant="outline" type="button">
                                Add intercompany JE
                            </Button>
                        </a>
                    )}
                    {actions.can_void && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={voidExpense}
                        >
                            <Ban className="size-4" />
                            Void
                        </Button>
                    )}
                    {actions.can_delete && (
                        <DeleteConfirmDialog
                            title="Delete this Expenses data?"
                            description={`This will permanently delete ${expense.payee ?? "Anonymous"} and cannot be undone.`}
                            confirmText="Delete Expenses"
                            onConfirm={remove}
                        />
                    )}
                </>
            }
        >
            <Head title={`Expense · ${expense.payee ?? expense.id}`} />

            <div className="space-y-4">
                <Link
                    href="/expenses"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to expenses
                </Link>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                        <CardDescription>
                            <StatusBadge value={expense.status} />
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-1.5 text-sm">
                        <Row
                            label="Amount"
                            value={formatCents(expense.net_payable_cents, currency)}
                        />
                        <Row
                            label="Payment"
                            value={
                                expense.payment_status === "paid"
                                    ? "Paid"
                                    : intercompanyVoucher?.status === "for_voucher"
                                    ? "Intercompany · For Voucher"
                                    : "Unpaid"
                            }
                        />
                        <Row
                            label="Branch"
                            value={expense.branch ?? "Company-wide"}
                        />
                        <Row
                            label="Reference"
                            value={expense.reference_no ?? "—"}
                        />
                        {expense.notes && (
                            <Row label="Notes" value={expense.notes} />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Expense Line Items</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs">
                                    <th className="px-4 py-2 font-medium">Particulars</th>
                                    <th className="px-4 py-2 text-right font-medium">Qty</th>
                                    <th className="px-4 py-2 font-medium">UOM</th>
                                    <th className="px-4 py-2 text-right font-medium">Unit price</th>
                                    <th className="px-4 py-2 text-right font-medium">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {expense.lines.map((line, index) => (
                                    <tr key={index} className="border-b last:border-0">
                                        <td className="px-4 py-2">
                                            <div>{line.particulars}</div>
                                            {line.account_code && (
                                                <div className="text-muted-foreground font-mono text-xs">
                                                    {line.account_code} · {line.account_name}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">{line.quantity.toLocaleString()}</td>
                                        <td className="px-4 py-2">{line.uom || "—"}</td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {formatCents(Math.round(line.unit_price * 100), currency)}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {formatCents(line.amount_cents, currency)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {journalEntry && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Journal entry {journalEntry.reference_no}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs">
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
                                    {journalEntry.lines.map((line, index) => (
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
                        </CardContent>
                    </Card>
                )}

                {expense.status === "posted" && (expense.payment_status === "unpaid" || intercompanyJournalEntry) && (
                    <LinkedIntercompanyEntry
                        source="expenses"
                        sourceId={expense.id}
                        date={expense.expense_date}
                        currency={currency}
                        entry={intercompanyJournalEntry}
                        voucher={intercompanyVoucher}
                        canMarkForVoucher={expense.payment_status === "unpaid"}
                        accounts={intercompanyAccounts}
                        branches={intercompanyBranches}
                    />
                )}

                {actions.can_mark_paid && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment</CardTitle>
                            <CardDescription>
                                This expense is still unpaid
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!showPayForm ? (
                                <Button
                                    size="sm"
                                    onClick={() => setShowPayForm(true)}
                                >
                                    Mark as paid
                                </Button>
                            ) : (
                                <form
                                    onSubmit={submitPayment}
                                    className="flex flex-wrap items-end gap-3"
                                >
                                    <div className="space-y-1">
                                        <label
                                            htmlFor="settlement_account"
                                            className="text-muted-foreground text-xs"
                                        >
                                            Paid from
                                        </label>
                                        <Select
                                            id="settlement_account"
                                            value={
                                                payForm.data.settlement_account
                                            }
                                            onChange={(event) =>
                                                payForm.setData(
                                                    "settlement_account",
                                                    event.target.value,
                                                )
                                            }
                                            className="w-auto min-w-40"
                                        >
                                            <option value="">
                                                Choose a source…
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
                                        {payForm.errors.settlement_account && (
                                            <p className="text-destructive text-xs">
                                                {
                                                    payForm.errors
                                                        .settlement_account
                                                }
                                            </p>
                                        )}
                                    </div>

                                    {payForm.data.settlement_account ===
                                        "cheque" && (
                                        <>
                                            <div className="space-y-1">
                                                <label
                                                    htmlFor="bank_name"
                                                    className="text-muted-foreground text-xs"
                                                >
                                                    Bank
                                                </label>
                                                <Input
                                                    id="bank_name"
                                                    value={
                                                        payForm.data.bank_name
                                                    }
                                                    onChange={(event) =>
                                                        payForm.setData(
                                                            "bank_name",
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-auto min-w-40"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <label
                                                    htmlFor="check_number"
                                                    className="text-muted-foreground text-xs"
                                                >
                                                    Check no.
                                                </label>
                                                <Input
                                                    id="check_number"
                                                    value={
                                                        payForm.data
                                                            .check_number
                                                    }
                                                    onChange={(event) =>
                                                        payForm.setData(
                                                            "check_number",
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-auto min-w-32"
                                                />
                                            </div>
                                        </>
                                    )}
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={payForm.processing}
                                    >
                                        Confirm payment
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setShowPayForm(false)}
                                    >
                                        Cancel
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                )}

                {expense.payment_status === "paid" && expense.paid_at && (
                    <p className="text-muted-foreground text-xs">
                        Paid {formatDateTime(expense.paid_at)}
                        {expense.paid_by ? ` by ${expense.paid_by}` : ""}
                    </p>
                )}

                {paymentJournalEntry && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Payment journal entry{" "}
                                {paymentJournalEntry.reference_no}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs">
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
                                    {paymentJournalEntry.lines.map(
                                        (line, index) => (
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
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                <p className="text-muted-foreground text-xs">
                    Recorded{" "}
                    {expense.created_by ? `by ${expense.created_by}` : ""}
                    {expense.posted_at &&
                        ` · Posted ${formatDateTime(expense.posted_at)}${expense.posted_by ? ` by ${expense.posted_by}` : ""}`}
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
