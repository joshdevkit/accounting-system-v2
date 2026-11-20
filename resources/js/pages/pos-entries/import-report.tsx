import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { formatCents, formatDate } from "@/lib/format";

const columns = [
    ["gross_sales", "VAT Inclusive"],
    ["taxable_amount", "VATable Sales"],
    ["exempted_amount", "VAT Exempt Sales"],
    ["vat_amount", "12% VAT"],
    ["discount_amount", "Discount"],
    ["withholding_tax_amount", "Withholding"],
    ["amount_due", "Amount Due"],
] as const;

type Entry = {
    id: number;
    business_date: string;
    pos_reference: string;
    guest_name: string | null;
    address: string | null;
    tin: string | null;
    business_source: string | null;
    journal_entry_id: number | null;
    sales: Record<(typeof columns)[number][0], number> & {
        missing_inputs: string[];
    };
};

export default function ImportReport({
    entries,
    currency,
    branchId,
}: {
    entries: Entry[];
    currency: string;
    branchId: number;
}) {
    return (
        <AuthenticatedLayout
            title="Generated sales report"
            description="Sales amount per invoice, in ascending reference order. Linked journals remain drafts, including unbalanced entries."
        >
            <Head title="POS import sales report" />
            <Link
                href={`/pos-entries?branch_id=${branchId}`}
                className="text-primary underline"
            >
                Back to POS entries
            </Link>
            <div className="mt-4 overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-sm">
                    <thead>
                        <tr>
                            {[
                                "Date",
                                "Reference No.",
                                "Guest Name",
                                "Address",
                                "TIN",
                                "Business Source",
                                ...columns.map(([, label]) => label),
                                "Draft JE",
                            ].map((label) => (
                                <th
                                    key={label}
                                    className="border-b bg-muted px-3 py-3 text-left whitespace-nowrap"
                                >
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {entries.map((entry) => (
                            <tr key={entry.id} className="border-b">
                                <td className="px-3 py-3 whitespace-nowrap">
                                    {formatDate(entry.business_date)}
                                </td>
                                <td className="px-3 py-3">
                                    <Link
                                        className="text-primary underline"
                                        href={`/pos-entries/${entry.id}`}
                                    >
                                        {entry.pos_reference}
                                    </Link>
                                </td>
                                {[
                                    entry.guest_name,
                                    entry.address,
                                    entry.tin,
                                    entry.business_source,
                                ].map((value, index) => (
                                    <td key={index} className="px-3 py-3">
                                        {value ?? "—"}
                                    </td>
                                ))}
                                {columns.map(([field]) => (
                                    <td
                                        key={field}
                                        className="px-3 py-3 text-right whitespace-nowrap tabular-nums"
                                    >
                                        {entry.sales.missing_inputs.length &&
                                        [
                                            "discount_amount",
                                            "amount_due",
                                        ].includes(field)
                                            ? "Rate required"
                                            : formatCents(
                                                  entry.sales[field],
                                                  currency,
                                              )}
                                    </td>
                                ))}
                                <td className="px-3 py-3">
                                    {entry.journal_entry_id ? (
                                        <Link
                                            className="text-primary underline"
                                            href={`/journal-entries/${entry.journal_entry_id}`}
                                        >
                                            View JE
                                        </Link>
                                    ) : (
                                        "—"
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="font-semibold">
                            <td colSpan={6} className="px-3 py-3">
                                Total ({entries.length} invoices)
                            </td>
                            {columns.map(([field]) => (
                                <td
                                    key={field}
                                    className="px-3 py-3 text-right whitespace-nowrap"
                                >
                                    {entries.some(
                                        (entry) =>
                                            entry.sales.missing_inputs.length >
                                            0,
                                    ) &&
                                    ["discount_amount", "amount_due"].includes(
                                        field,
                                    )
                                        ? "Rate required"
                                        : formatCents(
                                              entries.reduce(
                                                  (sum, entry) =>
                                                      sum + entry.sales[field],
                                                  0,
                                              ),
                                              currency,
                                          )}
                                </td>
                            ))}
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </AuthenticatedLayout>
    );
}
