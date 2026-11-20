import { Head, Link, router } from "@inertiajs/react";
import { PosWorkbookCalculation, type PosWorkbookPreview } from "@/components/pos/pos-workbook-preview";
import {
    ArrowLeft,
    Lock,
    Pencil,
    Receipt,
    RotateCcw,
    Send,
    TriangleAlert,
    Undo2,
} from "lucide-react";

import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import {
    formatCents,
    formatCentsSigned,
    formatDate,
    formatDateTime,
} from "@/lib/format";
import type { JournalEntryDetail } from "@/types/models";
import { DeleteDialog } from "@/components/journal/DeleteDialog";

type Props = {
    workbookPreview?: PosWorkbookPreview | null;
    entry: JournalEntryDetail;
    totals: {
        debits: number;
        credits: number;
        difference: number;
    };
    actions: {
        can_edit: boolean;
        can_post: boolean;
        can_unpost: boolean;
        can_reverse: boolean;
        can_delete: boolean;
    };
    currency: string;
};

export default function ShowJournalEntry({
    workbookPreview,
    entry,
    totals,
    actions,
    currency,
}: Props) {
    const run = (action: "post" | "unpost" | "reverse") => {
        if (
            action === "reverse" &&
            !window.confirm(
                `Reverse ${entry.reference_no}? A new posted entry with the debits and credits swapped will be created.`,
            )
        ) {
            return;
        }

        router.post(`/journal-entries/${entry.id}/${action}`, {});
    };

    const remove = () => {
        router.delete(`/journal-entries/${entry.id}`, {
            preserveScroll: false,
            onSuccess: () => router.get("/journal-entries"),
        });
    };

    const isPurchase =
        entry.source_type === "purchase" && entry.purchase !== null;

    return (
        <AuthenticatedLayout
            title={entry.reference_no}
            description={entry.description}
            actions={
                <>
                    {actions.can_edit && (
                        <Link href={`/journal-entries/${entry.id}/edit`}>
                            <Button size="sm" variant="outline">
                                <Pencil className="size-4" />
                                Edit
                            </Button>
                        </Link>
                    )}

                    {actions.can_post && (
                        <Button size="sm" onClick={() => run("post")}>
                            <Send className="size-4" />
                            Post entry
                        </Button>
                    )}

                    {actions.can_unpost && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => run("unpost")}
                        >
                            <Undo2 className="size-4" />
                            Return to draft
                        </Button>
                    )}

                    {actions.can_reverse && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => run("reverse")}
                        >
                            <RotateCcw className="size-4" />
                            Reverse
                        </Button>
                    )}

                    {actions.can_delete && (
                        <DeleteDialog
                            referenceNo={entry.reference_no}
                            onConfirm={remove}
                        />
                    )}
                </>
            }
        >
            <Head title={entry.reference_no} />
            {workbookPreview && <PosWorkbookCalculation preview={workbookPreview} currency={currency} />}

            <div className="space-y-4">
                <Link
                    href="/journal-entries"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to journal
                </Link>

                {entry.status === "draft" && totals.difference !== 0 && (
                    <Alert variant="destructive">
                        <TriangleAlert className="size-4" />

                        <AlertTitle>This draft does not balance</AlertTitle>

                        <AlertDescription>
                            Debits are {formatCents(totals.debits, currency)}{" "}
                            and credits are{" "}
                            {formatCents(totals.credits, currency)}: out by{" "}
                            {formatCents(Math.abs(totals.difference), currency)}
                            .
                        </AlertDescription>
                    </Alert>
                )}

                {entry.period && !entry.period.allows_posting && (
                    <Alert>
                        <Lock className="size-4" />

                        <AlertTitle>{entry.period.name} is closed</AlertTitle>

                        <AlertDescription>
                            Nothing new can be posted into this period until it
                            is reopened.
                        </AlertDescription>
                    </Alert>
                )}

                {entry.source_type === "pos" && (
                    <Alert>
                        <Receipt className="size-4" />

                        <AlertTitle>
                            Generated from POS summary{" "}
                            {entry.pos_reference ?? entry.source_reference}
                        </AlertTitle>

                        <AlertDescription>
                            Correct it by voiding or re-recording the POS day
                            summary, so the till report and the ledger stay
                            together.{" "}
                            {entry.pos_entry_id && (
                                <Link
                                    href={`/pos-entries/${entry.pos_entry_id}`}
                                    className="text-primary underline underline-offset-2"
                                >
                                    Open the POS summary
                                </Link>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {isPurchase && entry.purchase && (
                    <Alert>
                        <Receipt className="size-4" />

                        <AlertTitle>
                            Generated from purchase{" "}
                            {entry.purchase.reference_no ??
                                entry.source_reference ??
                                `#${entry.purchase.id}`}
                        </AlertTitle>

                        <AlertDescription>
                            This journal entry was generated from the purchase
                            transaction for{" "}
                            <span className="font-medium text-foreground">
                                {entry.contact?.name ?? "Unknown vendor"}
                            </span>
                            .
                        </AlertDescription>
                    </Alert>
                )}

                {entry.reverses && (
                    <Alert>
                        <AlertTitle>Reversing entry</AlertTitle>

                        <AlertDescription>
                            This entry reverses {entry.reverses}.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge value={entry.status} />

                            <span className="text-muted-foreground text-sm">
                                {formatDate(entry.transaction_date)}
                            </span>

                            {entry.branch && (
                                <span className="text-muted-foreground text-sm">
                                    · {entry.branch.name}
                                </span>
                            )}

                            {entry.period && (
                                <span className="text-muted-foreground text-sm">
                                    · {entry.period.name}
                                    {entry.fiscal_year
                                        ? ` (FY ${entry.fiscal_year.year})`
                                        : ""}
                                </span>
                            )}
                        </div>

                        <div className="text-muted-foreground flex items-center gap-4 text-xs">
                            <span>
                                Created {formatDateTime(entry.created_at)}
                                {entry.created_by
                                    ? ` by ${entry.created_by}`
                                    : ""}
                            </span>

                            {entry.posted_at && (
                                <span>
                                    Posted {formatDateTime(entry.posted_at)}
                                    {entry.posted_by
                                        ? ` by ${entry.posted_by}`
                                        : ""}
                                </span>
                            )}
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        {isPurchase && entry.purchase && (
                            <PurchaseDetails
                                purchase={entry.purchase}
                                currency={currency}
                            />
                        )}

                        <JournalSummary
                            lines={entry.lines}
                            totals={totals}
                            currency={currency}
                        />
                    </CardContent>
                </Card>

                <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 text-xs">
                    <span>
                        {entry.reversal
                            ? `Reversed by ${entry.reversal.reference_no} on ${formatDate(entry.reversal.transaction_date)}.`
                            : "Posted entries are never edited — reverse them and record a correction."}
                    </span>

                    {entry.reversal && (
                        <Link
                            href={`/journal-entries/${entry.reversal.id}`}
                            className="text-primary hover:underline"
                        >
                            View the reversing entry
                        </Link>
                    )}
                </div>

                <p className="text-muted-foreground text-xs">
                    Effect on the ledger:{" "}
                    {entry.lines
                        .filter(
                            (line) =>
                                line.debit_cents !== 0 ||
                                line.credit_cents !== 0,
                        )
                        .map(
                            (line) =>
                                `${line.account_code} ${formatCentsSigned(
                                    line.debit_cents - line.credit_cents,
                                    currency,
                                )}`,
                        )
                        .join(" · ") || "—"}
                </p>
            </div>
        </AuthenticatedLayout>
    );
}



function PurchaseDetails({
    purchase,
    currency,
}: {
    purchase: NonNullable<JournalEntryDetail["purchase"]>;
    currency: string;
}) {
    const total = purchase.lines.reduce(
        (sum, line) => sum + line.amount_cents,
        0,
    );

    return (
        <div className="border-b">
            <div className="bg-muted/20 border-b px-4 py-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 className="text-sm font-semibold">
                            Purchase Details
                        </h3>

                        <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-2 text-xs">
                            <span>
                                {purchase.contact?.name ??
                                    purchase.vendor_name ??
                                    "Unknown vendor"}
                            </span>

                            {purchase.reference_no && (
                                <>
                                    <span>·</span>
                                    <span>{purchase.reference_no}</span>
                                </>
                            )}

                            <span>·</span>

                            <span>{formatDate(purchase.purchase_date)}</span>
                        </div>
                    </div>

                    <span className="text-muted-foreground text-xs">
                        {purchase.lines.length}{" "}
                        {purchase.lines.length === 1 ? "item" : "items"}
                    </span>
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs">
                            <th className="px-4 py-2 font-medium">#</th>

                            <th className="px-4 py-2 font-medium">
                                Particulars
                            </th>

                            <th className="px-4 py-2 text-right font-medium">
                                Qty
                            </th>

                            <th className="px-4 py-2 font-medium">UOM</th>

                            <th className="px-4 py-2 text-right font-medium">
                                Unit Price
                            </th>

                            <th className="px-4 py-2 text-right font-medium">
                                Amount
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        {purchase.lines.map((line, index) => (
                            <tr
                                key={line.id}
                                className="border-b last:border-0"
                            >
                                <td className="text-muted-foreground px-4 py-2.5">
                                    {index + 1}
                                </td>

                                <td className="px-4 py-2.5">
                                    <div className="font-medium">
                                        {line.particulars}
                                    </div>

                                    {line.account && (
                                        <div className="text-muted-foreground mt-0.5 font-mono text-xs">
                                            {line.account.code} ·{" "}
                                            {line.account.name}
                                        </div>
                                    )}
                                </td>

                                <td className="px-4 py-2.5 text-right tabular-nums">
                                    {line.quantity.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })}
                                </td>

                                <td className="text-muted-foreground px-4 py-2.5">
                                    {line.uom}
                                </td>

                                <td className="px-4 py-2.5 text-right tabular-nums">
                                    {formatCents(
                                        Math.round(line.unit_price * 100),
                                        currency,
                                    )}
                                </td>

                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                    {formatCents(line.amount_cents, currency)}
                                </td>
                            </tr>
                        ))}

                        {purchase.lines.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="text-muted-foreground px-4 py-8 text-center"
                                >
                                    No purchase line items found.
                                </td>
                            </tr>
                        )}
                    </tbody>

                    <tfoot>
                        <tr className="bg-muted/20 border-t">
                            <td
                                colSpan={5}
                                className="px-4 py-2.5 text-right font-medium"
                            >
                                Total
                            </td>

                            <td className="px-4 py-2.5 text-right font-semibold tabular-nums">
                                {formatCents(total, currency)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}

function JournalSummary({
    lines,
    totals,
    currency,
}: {
    lines: JournalEntryDetail["lines"];
    totals: {
        debits: number;
        credits: number;
    };
    currency: string;
}) {
    return (
        <div>
            <div className="bg-muted/20 border-b px-4 py-3">
                <h3 className="text-sm font-semibold">Journal Summary</h3>

                <p className="text-muted-foreground mt-0.5 text-xs">
                    Accounts posted to the general ledger
                </p>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs">
                            <th className="px-4 py-2 font-medium">Account</th>

                            <th className="px-4 py-2 font-medium">Memo</th>

                            <th className="px-4 py-2 text-right font-medium">
                                Debit
                            </th>

                            <th className="px-4 py-2 text-right font-medium">
                                Credit
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        {lines.map((line) => (
                            <tr
                                key={line.id}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-2.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-muted-foreground font-mono text-xs tabular-nums">
                                            {line.account_code ?? "—"}
                                        </span>

                                        <span className="font-medium">
                                            {line.account_name ??
                                                "Removed account"}
                                        </span>

                                        {line.account_category && (
                                            <StatusBadge
                                                value={line.account_category}
                                                className="h-4 px-1.5 text-[10px]"
                                            />
                                        )}

                                        {line.account_inactive && (
                                            <span className="text-destructive text-xs">
                                                inactive
                                            </span>
                                        )}
                                    </div>
                                </td>

                                <td className="text-muted-foreground px-4 py-2.5">
                                    {line.description ?? "—"}
                                </td>

                                <td className="px-4 py-2.5 text-right tabular-nums">
                                    {line.debit_cents
                                        ? formatCents(
                                              line.debit_cents,
                                              currency,
                                          )
                                        : "—"}
                                </td>

                                <td className="px-4 py-2.5 text-right tabular-nums">
                                    {line.credit_cents
                                        ? formatCents(
                                              line.credit_cents,
                                              currency,
                                          )
                                        : "—"}
                                </td>
                            </tr>
                        ))}

                        {lines.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="text-muted-foreground px-4 py-8 text-center"
                                >
                                    No journal lines found.
                                </td>
                            </tr>
                        )}
                    </tbody>

                    <tfoot>
                        <tr className="bg-muted/30 border-t font-semibold">
                            <td className="px-4 py-2.5" colSpan={2}>
                                Totals
                            </td>

                            <td className="px-4 py-2.5 text-right tabular-nums">
                                {formatCents(totals.debits, currency)}
                            </td>

                            <td className="px-4 py-2.5 text-right tabular-nums">
                                {formatCents(totals.credits, currency)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}
