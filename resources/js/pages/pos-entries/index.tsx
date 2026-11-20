import { Head, Link, router } from "@inertiajs/react";
import { ImportIcon, Plus, Receipt, Search } from "lucide-react";
import { useState, type FormEvent } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Pagination } from "@/components/ui/pagination";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCents, formatCentsSigned, formatDate } from "@/lib/format";
import { compactQuery } from "@/lib/utils";
import type { PaginationLink, PosEntryRow } from "@/types/models";
import { create, index, show } from "@/routes/pos-entries";
import { show as JournalEntryShow } from "@/routes/journal-entries";
import PosImportDialog from "@/components/pos/PosImportDialog";
type Filters = {
    status: string;
    branch_id: number | null;
    from: string;
    to: string;
    q: string;
};

type Props = {
    entries: PosEntryRow[];
    pagination: PaginationLink[];
    totals: Record<string, number>;
    filters: Filters;
    branches: { id: number; name: string; code: string; is_active: boolean }[];
    paymentLabels: Record<string, string>;
    currency: string;
};

export default function PosEntriesIndex({
    entries,
    pagination,
    totals,
    filters,
    branches,
    currency,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const apply = (next: Partial<Filters>) => {
        router.get(index(), compactQuery({ ...filters, ...next, q: search }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const [importOpen, setImportOpen] = useState(false);

    return (
        <AuthenticatedLayout
            title="POS entries"
            description="Daily terminal summaries, and the ledger entry each one produced"
            actions={
                <>
                    <Button
                        variant="secondary"
                        onClick={() => setImportOpen(true)}
                    >
                        <ImportIcon className="size-4" />
                        Import POS Data
                    </Button>
                    <Link
                        href={create()}
                        className={buttonVariants({ size: "sm" })}
                    >
                        <Plus className="size-4" />
                        Record entry
                    </Link>
                </>
            }
        >
            <Head title="POS entries" />

            <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {[
                    { label: "Gross sales", key: "gross_sales" },
                    { label: "Discounts", key: "discount_amount" },
                    { label: "VAT", key: "vat_amount" },
                    { label: "Net sales", key: "net_sales" },
                ].map((item) => (
                    <div
                        key={item.key}
                        className="bg-card rounded-lg border px-4 py-3"
                    >
                        <p className="text-muted-foreground text-xs">
                            {item.label}
                        </p>
                        <p className="mt-0.5 truncate text-lg font-semibold tabular-nums">
                            {formatCents(totals[item.key] ?? 0, currency)}
                        </p>
                    </div>
                ))}
            </div>

            <Card>
                <CardContent className="p-0">
                    <div className="flex flex-wrap items-center gap-2 border-b p-3">
                        <div className="flex items-center gap-1 overflow-x-auto rounded-md border p-1">
                            {branches.map((branch) => {
                                const isActive =
                                    filters.branch_id === branch.id ||
                                    (!filters.branch_id &&
                                        branch.id === branches[0]?.id);
                                return (
                                    <button
                                        key={branch.id}
                                        type="button"
                                        onClick={() =>
                                            apply({ branch_id: branch.id })
                                        }
                                        className={[
                                            "shrink-0 rounded px-2.5 py-1 text-xs font-medium whitespace-nowrap transition-colors",
                                            isActive
                                                ? "bg-primary text-primary-foreground"
                                                : "text-muted-foreground hover:bg-muted hover:text-foreground",
                                        ].join(" ")}
                                    >
                                        {branch.code}
                                    </button>
                                );
                            })}
                        </div>
                        <form
                            onSubmit={(event: FormEvent) => {
                                event.preventDefault();
                                apply({});
                            }}
                            className="flex min-w-52 flex-1 items-center gap-2"
                        >
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search POS reference"
                                    aria-label="Search POS entries"
                                    className="pl-8"
                                />
                            </div>
                        </form>
                        <Select
                            aria-label="Status"
                            className="w-auto min-w-28"
                            value={filters.status}
                            onChange={(event) =>
                                apply({ status: event.target.value })
                            }
                        >
                            <option value="">Any status</option>
                            <option value="draft">Draft</option>
                            <option value="posted">Posted</option>
                            <option value="voided">Voided</option>
                        </Select>
                        <Input
                            type="date"
                            aria-label="From date"
                            className="w-auto"
                            value={filters.from}
                            onChange={(event) =>
                                apply({ from: event.target.value })
                            }
                        />
                        <Input
                            type="date"
                            aria-label="To date"
                            className="w-auto"
                            value={filters.to}
                            onChange={(event) =>
                                apply({ to: event.target.value })
                            }
                        />

                        {(filters.status ||
                            filters.branch_id ||
                            filters.from ||
                            filters.to ||
                            filters.q) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch("");
                                    router.get(
                                        index(),
                                        {},
                                        {
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                Reset
                            </Button>
                        )}
                    </div>

                    {entries.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-4 py-14 text-center">
                            <div className="bg-muted flex size-9 items-center justify-center rounded-full">
                                <Receipt className="text-muted-foreground size-4" />
                            </div>
                            <p className="text-sm font-medium">
                                No POS summaries yet
                            </p>
                            <p className="text-muted-foreground max-w-sm text-xs">
                                Record a day summary per terminal to bring the
                                till reports into the ledger.
                            </p>
                            <Link
                                href="/pos-entries/create"
                                className={buttonVariants({ size: "sm" })}
                            >
                                <Plus className="size-4" />
                                Record entry
                            </Link>
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Reference
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Net sales
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Tendered
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Variance
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Ledger
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.map((entry) => (
                                    <tr
                                        key={entry.id}
                                        className="hover:bg-muted/40 border-b last:border-0"
                                    >
                                        <td className="px-4 py-2.5 whitespace-nowrap">
                                            {formatDate(entry.business_date)}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <Link
                                                href={show(entry.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {entry.pos_reference}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums">
                                            {formatCents(
                                                entry.amounts.net_sales,
                                                currency,
                                            )}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2.5 text-right tabular-nums">
                                            {formatCents(
                                                entry.tendered_cents,
                                                currency,
                                            )}
                                        </td>
                                        <td
                                            className={
                                                entry.variance_cents === 0
                                                    ? "text-muted-foreground/60 px-4 py-2.5 text-right tabular-nums"
                                                    : "text-destructive px-4 py-2.5 text-right font-medium tabular-nums"
                                            }
                                        >
                                            {entry.variance_cents === 0
                                                ? "—"
                                                : formatCentsSigned(
                                                      entry.variance_cents,
                                                      currency,
                                                  )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {entry.journal_entry ? (
                                                <Link
                                                    href={JournalEntryShow(
                                                        entry.journal_entry.id,
                                                    )}
                                                    className="font-mono text-xs hover:underline"
                                                >
                                                    {
                                                        entry.journal_entry
                                                            .reference_no
                                                    }
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">
                                                    not posted
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <StatusBadge value={entry.status} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    <Pagination links={pagination} />
                    <PosImportDialog
                        open={importOpen}
                        onOpenChange={setImportOpen}
                        branches={branches}
                    />
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}
