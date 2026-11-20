import { Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Pagination } from "@/components/ui/pagination";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from "@/components/ui/combobox";
import { formatCents, formatDate } from "@/lib/format";
import { compactQuery } from "@/lib/utils";
import type { PaginationLink } from "@/types/models";

type Entry = {
    id: number;
    vendor_name: string | null;
    date: string | null;
    purchase_date: string;
    reference_no: string | null;
    amount_cents: number;
    check_number: string | null;
    released_at: string | null;
};

type Filters = {
    contact_id: number | null;
    from: string;
    to: string;
};

type Props = {
    entries: Entry[];
    pagination: PaginationLink[];
    total_cents: number;
    filters: Filters;
    vendors: { id: number; name: string }[];
    currency: string;
};

export default function VendorLedger({
    entries,
    pagination,
    total_cents,
    filters,
    vendors,
    currency,
}: Props) {
    const apply = (next: Partial<Filters>) => {
        router.get("/vendor-ledger", compactQuery({ ...filters, ...next }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const hasFilters = filters.contact_id || filters.from || filters.to;

    return (
        <AuthenticatedLayout
            title="Accounts Payable - Vendor Ledger"
            description="Recent purchases"
        >
            <Head title="Accounts Payable - Vendor Ledger" />

            <Card>
                <CardContent className="p-0">
                    <div className="flex flex-wrap items-center gap-2 border-b p-3">
                        <Combobox
                            items={vendors}
                            value={
                                vendors.find(
                                    (vendor) => vendor.id === filters.contact_id,
                                ) ?? null
                            }
                            onValueChange={(vendor) =>
                                apply({ contact_id: vendor ? vendor.id : null })
                            }
                            itemToStringLabel={(vendor) => vendor?.name ?? ""}
                        >
                            <ComboboxInput
                                aria-label="Vendor"
                                placeholder="All vendors"
                                className="w-91 min-w-48"
                            />
                            <ComboboxContent>
                                <ComboboxList>
                                    {vendors.map((vendor) => (
                                        <ComboboxItem key={vendor.id} value={vendor}>
                                            {vendor.name}
                                        </ComboboxItem>
                                    ))}
                                </ComboboxList>
                            </ComboboxContent>
                        </Combobox>

                        <Input
                            type="date"
                            aria-label="Released from"
                            className="w-auto"
                            value={filters.from}
                            onChange={(event) => apply({ from: event.target.value })}
                        />
                        <Input
                            type="date"
                            aria-label="Released to"
                            className="w-auto"
                            value={filters.to}
                            onChange={(event) => apply({ to: event.target.value })}
                        />

                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.get("/vendor-ledger", {}, { preserveScroll: true })
                                }
                            >
                                Reset
                            </Button>
                        )}

                        <p className="text-muted-foreground ml-auto text-xs">
                            Total released{" "}
                            <span className="text-foreground font-semibold tabular-nums">
                                {formatCents(total_cents, currency)}
                            </span>
                        </p>
                    </div>

                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-b text-left text-xs">
                                <th className="px-4 py-2 font-medium">Purchase Date</th>
                                <th className="px-4 py-2 font-medium">SI No. (Reference)</th>
                                <th className="px-4 py-2 text-right font-medium">Amount</th>
                                <th className="px-4 py-2 font-medium">Cheque No.</th>
                                <th className="px-4 py-2 text-right font-medium">Date Released</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground px-4 py-12 text-center text-sm"
                                    >
                                        No released purchases found.
                                    </td>
                                </tr>
                            )}
                            {entries.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="hover:bg-muted/30 cursor-pointer border-b last:border-0"
                                    onClick={() => router.get(`/purchases/${entry.id}`)}
                                >
                                    <td className="text-muted-foreground px-4 py-2 whitespace-nowrap">
                                        {formatDate(entry.purchase_date)}
                                    </td>
                                    <td className="px-4 py-2">{entry.reference_no ?? "—"}</td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {formatCents(entry.amount_cents, currency)}
                                    </td>
                                    <td className="px-4 py-2">{entry.check_number ?? "—"}</td>
                                    <td className="text-muted-foreground px-4 py-2 text-right whitespace-nowrap">
                                        {entry.released_at ? formatDate(entry.released_at) : "—"}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <Pagination links={pagination} />
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}