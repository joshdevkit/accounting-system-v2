import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowLeft,
    Ban,
    Pencil,
    Send,
    Trash2,
    TriangleAlert,
} from "lucide-react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import {
    PosWorkbookCalculation,
    type PosWorkbookPreview,
} from "@/components/pos/pos-workbook-preview";
import {
    formatCents,
    formatCentsSigned,
    formatDate,
    formatDateTime,
    toTitleCase,
} from "@/lib/format";
import type {
    LinkedJournalEntry,
    PosEntryDetail,
    PosPostingMappingRow,
} from "@/types/models";

type Props = {
    importReportUrl: string | null;
    workbookPreview: PosWorkbookPreview | null;
    pos: PosEntryDetail;
    journalEntry: LinkedJournalEntry | null;
    actions: {
        can_edit: boolean;
        can_post: boolean;
        can_void: boolean;
        can_delete: boolean;
    };
    mapping: PosPostingMappingRow[];
    currency: string;
};

export default function ShowPosEntry({
    importReportUrl,
    workbookPreview,
    pos,
    journalEntry,
    actions,
    mapping,
    currency,
}: Props) {
    const post = () => router.post(`/pos-entries/${pos.id}/post`, {});

    const voidEntry = () => {
        if (
            !window.confirm(
                `Void ${pos.pos_reference}? ${
                    journalEntry
                        ? `A reversing journal entry for ${journalEntry.reference_no} will be posted.`
                        : "Nothing has been posted for it yet."
                }`,
            )
        ) {
            return;
        }

        router.post(`/pos-entries/${pos.id}/void`, {});
    };

    const remove = () => {
        if (!window.confirm(`Delete draft summary ${pos.pos_reference}?`)) {
            return;
        }

        router.delete(`/pos-entries/${pos.id}`, {
            onSuccess: () => router.get("/pos-entries"),
        });
    };

    const labelFor = (field: string) =>
        mapping.find((row) => row.field === field)?.label ?? toTitleCase(field);

    return (
        <AuthenticatedLayout
            title={`${pos.pos_reference} · ${formatDate(pos.business_date)}`}
            description={
                pos.branch
                    ? `${pos.branch.name} (${pos.branch.code})`
                    : "No branch"
            }
            actions={
                <>
                    {actions.can_edit && (
                        <Link href={`/pos-entries/${pos.id}/edit`}>
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
                        <Button size="sm" variant="outline" onClick={voidEntry}>
                            <Ban className="size-4" />
                            Void
                        </Button>
                    )}
                    {actions.can_delete && (
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={remove}
                            aria-label="Delete draft"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    )}
                </>
            }
        >
            <Head title={`POS ${pos.pos_reference}`} />
            {importReportUrl && (
                <Link className="text-primary underline" href={importReportUrl}>
                    View imported sales report
                </Link>
            )}

            <div className="space-y-4">
                <Link
                    href="/pos-entries"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to POS entries
                </Link>

                {pos.status === "draft" && (
                    <Alert>
                        <AlertTitle>
                            This summary is not in the ledger yet
                        </AlertTitle>
                        <AlertDescription>
                            {actions.can_post
                                ? "Review the totals and account mappings before posting."
                                : "Review the calculated sales breakdown and journal preview below."}
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Guest and sales details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        {Object.entries(pos.sales_details).map(
                            ([field, value]) => (
                                <div key={field}>
                                    <div className="text-muted-foreground text-sm">
                                        {toTitleCase(field)}
                                    </div>
                                    <div>
                                        {value
                                            ? [
                                                  "business_source",
                                                  "vat_classification",
                                                  "discount_type",
                                              ].includes(field)
                                                ? toTitleCase(value)
                                                : value
                                            : "Unspecified"}
                                    </div>
                                </div>
                            ),
                        )}
                    </CardContent>
                </Card>

                {workbookPreview && (
                    <PosWorkbookCalculation
                        preview={workbookPreview}
                        currency={currency}
                    />
                )}

                {!workbookPreview && pos.variance_cents !== 0 && (
                    <Alert variant="destructive">
                        <TriangleAlert className="size-4" />
                        <AlertTitle>
                            Tendered {pos.variance_cents > 0 ? "over" : "short"}{" "}
                            by{" "}
                            {formatCents(
                                Math.abs(pos.variance_cents),
                                currency,
                            )}
                        </AlertTitle>
                        <AlertDescription>
                            {formatCents(pos.tendered_cents, currency)} was
                            tendered against{" "}
                            {formatCents(pos.expected_cents, currency)}{" "}
                            expected. The difference posts to the over/short
                            account.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recorded figures</CardTitle>
                            <CardDescription>
                                As reported by the terminal
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-1.5 text-sm">
                            {Object.entries(pos.amounts)
                                .filter(([, value]) => value !== 0)
                                .map(([field, value]) => (
                                    <div
                                        key={field}
                                        className="flex items-center justify-between gap-2 border-b pb-1.5 last:border-0 last:pb-0"
                                    >
                                        <span className="text-muted-foreground">
                                            {labelFor(field)}
                                        </span>
                                        <span className="tabular-nums">
                                            {formatCents(value, currency)}
                                        </span>
                                    </div>
                                ))}
                            {Object.values(pos.amounts).every(
                                (value) => value === 0,
                            ) && (
                                <p className="text-muted-foreground text-xs">
                                    No amounts recorded.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Tendered by method</CardTitle>
                            <CardDescription>
                                Imported payment amounts
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-1.5 text-sm">
                            {pos.tenders
                                .filter((tender) => tender.cents !== 0)
                                .map((tender) => {
                                    const row = mapping.find(
                                        (candidate) =>
                                            candidate.field === tender.field,
                                    );

                                    return (
                                        <div
                                            key={tender.field}
                                            className="flex items-center justify-between gap-2 border-b pb-1.5 last:border-0 last:pb-0"
                                        >
                                            <span className="text-muted-foreground">
                                                {tender.label}
                                                {row?.code && (
                                                    <span className="ml-1.5 font-mono text-[11px]">
                                                        {row.code}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatCents(
                                                    tender.cents,
                                                    currency,
                                                )}
                                            </span>
                                        </div>
                                    );
                                })}
                            {pos.tenders.every(
                                (tender) => tender.cents === 0,
                            ) && (
                                <p className="text-muted-foreground text-xs">
                                    Nothing tendered.
                                </p>
                            )}
                            <div className="mt-2 flex items-center justify-between gap-2 border-t pt-2 font-medium">
                                <span>Total tendered</span>
                                <span className="tabular-nums">
                                    {formatCents(pos.tendered_cents, currency)}
                                </span>
                            </div>
                            <div className="text-muted-foreground flex items-center justify-between gap-2 text-xs">
                                <span>Expected amount due</span>
                                <span className="tabular-nums">
                                    {formatCents(pos.expected_cents, currency)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-2 text-xs">
                                <span className="text-muted-foreground">
                                    Variance
                                </span>
                                <span
                                    className={
                                        pos.variance_cents === 0
                                            ? "tabular-nums"
                                            : "text-destructive tabular-nums"
                                    }
                                >
                                    {pos.variance_cents === 0
                                        ? "—"
                                        : formatCentsSigned(
                                              pos.variance_cents,
                                              currency,
                                          )}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row flex-wrap items-center justify-between gap-2 space-y-0">
                        <div>
                            <CardTitle>
                                {journalEntry
                                    ? `Journal entry ${journalEntry.reference_no}`
                                    : "No journal entry yet"}
                            </CardTitle>
                            <CardDescription>
                                {journalEntry
                                    ? `${journalEntry.lines.length} lines · ${journalEntry.period ?? "no period"}${journalEntry.branch ? ` · ${journalEntry.branch}` : ""}`
                                    : "Post this summary to write the ledger entry"}
                            </CardDescription>
                        </div>
                        {journalEntry && (
                            <div className="flex items-center gap-2">
                                <StatusBadge value={journalEntry.status} />
                                <Link
                                    href={`/journal-entries/${journalEntry.id}`}
                                    className="text-primary text-xs font-medium hover:underline"
                                >
                                    Open entry
                                </Link>
                            </div>
                        )}
                    </CardHeader>
                    <CardContent className="p-0">
                        {journalEntry && (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs">
                                        <th className="px-4 py-2 font-medium">
                                            Account
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Memo
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
                                    {journalEntry.lines.map((line) => (
                                        <tr
                                            key={line.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-4 py-2">
                                                <span className="text-muted-foreground font-mono text-xs">
                                                    {line.account_code ?? "—"}
                                                </span>{" "}
                                                <span className="font-medium">
                                                    {line.account_name ?? "—"}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-2">
                                                {line.description ?? "—"}
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
                                <tfoot>
                                    <tr className="bg-muted/30 border-t font-semibold">
                                        <td className="px-4 py-2" colSpan={2}>
                                            Totals
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {formatCents(
                                                journalEntry.totals.debits,
                                                currency,
                                            )}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {formatCents(
                                                journalEntry.totals.credits,
                                                currency,
                                            )}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        )}
                    </CardContent>
                </Card>

                <div className="text-muted-foreground flex flex-wrap items-center gap-x-6 gap-y-1 text-xs">
                    <span>
                        Recorded {formatDateTime(pos.created_at)}
                        {pos.created_by ? ` by ${pos.created_by}` : ""}
                    </span>
                    {pos.posted_at && (
                        <span>
                            Posted {formatDateTime(pos.posted_at)}
                            {pos.posted_by ? ` by ${pos.posted_by}` : ""}
                        </span>
                    )}
                    {pos.voided_at && (
                        <span>
                            Voided {formatDateTime(pos.voided_at)}
                            {pos.voided_by ? ` by ${pos.voided_by}` : ""}
                        </span>
                    )}
                </div>

                {pos.notes && (
                    <p className="bg-card text-muted-foreground rounded-lg border px-4 py-3 text-sm whitespace-pre-line">
                        {pos.notes}
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
