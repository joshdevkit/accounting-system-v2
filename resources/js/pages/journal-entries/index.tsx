import { Head, Link, router } from "@inertiajs/react";
import { Plus, Search, TriangleAlert } from "lucide-react";
import { useState, type FormEvent } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button, buttonVariants } from "@/components/ui/button";
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
import { StatusBadge } from "@/components/ui/status-badge";
import { cn, compactQuery } from "@/lib/utils";
import { formatCents, formatDate } from "@/lib/format";
import type { JournalEntryRow, PaginationLink } from "@/types/models";

const STATUS_OPTIONS = [
    { value: "draft", label: "Draft" },
    { value: "posted", label: "Posted" },
    { value: "released", label: "Released" },
    { value: "reversed", label: "Reversed" },
];

const SOURCE_OPTIONS = [
    { value: "manual", label: "Manual" },
    { value: "pos", label: "POS" },
    { value: "expense", label: "Expenses" },
    { value: "sale", label: "Sales" },
    { value: "purchase", label: "Purchase" },
];

// Lets the branch filter offer an explicit "All branches" choice.
const ALL_BRANCHES = { id: 0, name: "All branches", code: "" };

type Filters = {
    status: string;
    source_type: string;
    branch_id: number | null;
    period_id: number | null;
    from: string;
    to: string;
    q: string;
};

type EntryRow = JournalEntryRow & {
    vendor: string | null;
    cheque_number: string | null;
    display_status: string;
};

type Props = {
    entries: EntryRow[];
    pagination: PaginationLink[];
    summary: {
        entries: number;
        drafts: number;
        debits: number;
        credits: number;
        difference: number;
    };
    filters: Filters;
    branches: { id: number; name: string; code: string }[];
    periods: { id: number; label: string; status: string }[];
    currency: string;
};

export default function JournalEntriesIndex({
    entries,
    pagination,
    summary,
    filters,
    branches,
    periods,
    currency,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const branchItems = [ALL_BRANCHES, ...branches];

    const apply = (next: Partial<Filters>) => {
        router.get(
            "/journal-entries",
            compactQuery({ ...filters, ...next, q: search }),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        apply({});
    };

    return (
        <AuthenticatedLayout
            title="Journal entries"
            description="Every posting in the ledger, manual or from the POS"
            actions={
                <Link
                    href="/journal-entries/create"
                    className={buttonVariants({ size: "sm" })}
                >
                    <Plus className="size-4" />
                    New entry
                </Link>
            }
        >
            <Head title="Journal entries" />

            <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {[
                    { label: "Entries", value: String(summary.entries) },
                    { label: "Drafts", value: String(summary.drafts) },
                    {
                        label: "Total debits",
                        value: formatCents(summary.debits, currency),
                    },
                    {
                        label: "Total credits",
                        value: formatCents(summary.credits, currency),
                    },
                ].map((item) => (
                    <div
                        key={item.label}
                        className="bg-card rounded-lg border px-4 py-3"
                    >
                        <p className="text-muted-foreground text-xs">
                            {item.label}
                        </p>
                        <p className="mt-0.5 truncate text-lg font-semibold tabular-nums">
                            {item.value}
                        </p>
                    </div>
                ))}
            </div>

            {summary.difference !== 0 && (
                <div className="border-destructive/30 bg-destructive/5 text-destructive mb-4 flex items-start gap-2 rounded-lg border px-4 py-3 text-sm">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Debits and credits across this filter differ by{" "}
                        {formatCents(Math.abs(summary.difference), currency)}.
                        Unposted drafts may be the cause.
                    </span>
                </div>
            )}

            <Card>
                <CardContent className="p-0">
                    <div className="flex flex-wrap items-center gap-2 border-b p-3">
                        <form
                            onSubmit={submitSearch}
                            className="flex min-w-52 flex-1 items-center gap-2"
                        >
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Reference, description or POS id"
                                    aria-label="Search journal entries"
                                    className="pl-8"
                                />
                            </div>
                        </form>

                        <Combobox
                            items={STATUS_OPTIONS}
                            value={
                                STATUS_OPTIONS.find(
                                    (option) => option.value === filters.status,
                                ) ?? null
                            }
                            onValueChange={(option) =>
                                apply({ status: option ? option.value : "" })
                            }
                            itemToStringLabel={(option) => option?.label ?? ""}
                        >
                            <ComboboxInput
                                aria-label="Status"
                                placeholder="Any status"
                                className="w-auto min-w-28"
                            />
                            <ComboboxContent>
                                <ComboboxList>
                                    {STATUS_OPTIONS.map((option) => (
                                        <ComboboxItem
                                            key={option.value}
                                            value={option}
                                        >
                                            {option.label}
                                        </ComboboxItem>
                                    ))}
                                </ComboboxList>
                            </ComboboxContent>
                        </Combobox>

                        <Combobox
                            items={SOURCE_OPTIONS}
                            value={
                                SOURCE_OPTIONS.find(
                                    (option) =>
                                        option.value === filters.source_type,
                                ) ?? null
                            }
                            onValueChange={(option) =>
                                apply({
                                    source_type: option ? option.value : "",
                                })
                            }
                            itemToStringLabel={(option) => option?.label ?? ""}
                        >
                            <ComboboxInput
                                aria-label="Source"
                                placeholder="Any source"
                                className="w-auto min-w-28"
                            />
                            <ComboboxContent>
                                <ComboboxList>
                                    {SOURCE_OPTIONS.map((option) => (
                                        <ComboboxItem
                                            key={option.value}
                                            value={option}
                                        >
                                            {option.label}
                                        </ComboboxItem>
                                    ))}
                                </ComboboxList>
                            </ComboboxContent>
                        </Combobox>

                        <Combobox
                            items={branchItems}
                            value={
                                branches.find(
                                    (branch) =>
                                        String(branch.id) ===
                                        String(filters.branch_id),
                                ) ?? ALL_BRANCHES
                            }
                            onValueChange={(branch) =>
                                apply({
                                    branch_id:
                                        branch && branch.id !== 0
                                            ? branch.id
                                            : null,
                                })
                            }
                            itemToStringLabel={(branch) => branch?.name ?? ""}
                        >
                            <ComboboxInput
                                aria-label="Branch"
                                placeholder="All branches"
                                className="w-auto min-w-32"
                            />
                            <ComboboxContent>
                                <ComboboxList>
                                    {branchItems.map((branch) => (
                                        <ComboboxItem
                                            key={branch.id}
                                            value={branch}
                                        >
                                            {branch.name}
                                        </ComboboxItem>
                                    ))}
                                </ComboboxList>
                            </ComboboxContent>
                        </Combobox>

                        <Combobox
                            items={periods}
                            value={
                                periods.find(
                                    (period) =>
                                        String(period.id) ===
                                        String(filters.period_id),
                                ) ?? null
                            }
                            onValueChange={(period) =>
                                apply({ period_id: period ? period.id : null })
                            }
                            itemToStringLabel={(period) => period?.label ?? ""}
                        >
                            <ComboboxInput
                                aria-label="Period"
                                placeholder="All periods"
                                className="w-auto min-w-36"
                            />
                            <ComboboxContent>
                                <ComboboxList>
                                    {periods.map((period) => (
                                        <ComboboxItem
                                            key={period.id}
                                            value={period}
                                        >
                                            {period.label}
                                        </ComboboxItem>
                                    ))}
                                </ComboboxList>
                            </ComboboxContent>
                        </Combobox>

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
                            filters.source_type ||
                            filters.branch_id ||
                            filters.period_id ||
                            filters.from ||
                            filters.to ||
                            filters.q) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch("");
                                    router.get(
                                        "/journal-entries",
                                        {},
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                Reset
                            </Button>
                        )}
                    </div>

                    {entries.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-4 py-14 text-center">
                            <p className="text-sm font-medium">
                                No journal entries here yet
                            </p>
                            <p className="text-muted-foreground max-w-sm text-xs">
                                Post a POS day summary or record a manual entry
                                to start the ledger.
                            </p>
                            <Link
                                href="/journal-entries/create"
                                className={cn(
                                    buttonVariants({ size: "sm" }),
                                    "mt-2",
                                )}
                            >
                                <Plus className="size-4" />
                                New entry
                            </Link>
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">
                                        Reference
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Vendor
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Description
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Period
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Amount
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Cheque no.
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.map((entry) => (
                                    <tr
                                        key={entry.id}
                                        className="hover:bg-muted/40 border-b last:border-0"
                                    >
                                        <td className="px-4 py-2.5">
                                            <Link
                                                href={`/journal-entries/${entry.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {entry.reference_no}
                                            </Link>
                                            <span className="text-muted-foreground ml-2 text-xs">
                                                {entry.source_type === "pos"
                                                    ? `POS ${entry.pos_reference ?? ""}`.trim()
                                                    : `${entry.line_count} line${entry.line_count === 1 ? "" : "s"}`}
                                            </span>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2.5 whitespace-nowrap">
                                            {formatDate(entry.transaction_date)}
                                        </td>
                                        <td className="max-w-48 px-4 py-2.5">
                                            <span className="line-clamp-1">
                                                {entry.vendor ?? "—"}
                                            </span>
                                        </td>
                                        <td className="max-w-md px-4 py-2.5">
                                            <span className="line-clamp-1">
                                                {entry.description}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {entry.period ? (
                                                <span className="inline-flex items-center gap-1.5 text-xs">
                                                    {entry.period.name}
                                                    {entry.period.status ===
                                                        "closed" && (
                                                        <StatusBadge
                                                            value="closed"
                                                            className="h-4 px-1.5 text-[10px]"
                                                        />
                                                    )}
                                                </span>
                                            ) : (
                                                "—"
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 text-right">
                                            <span className="block font-medium tabular-nums">
                                                {formatCents(
                                                    entry.debit_cents,
                                                    currency,
                                                )}
                                            </span>
                                            {!entry.is_balanced && (
                                                <span className="text-destructive block text-xs tabular-nums">
                                                    out by{" "}
                                                    {formatCents(
                                                        Math.abs(
                                                            entry.debit_cents -
                                                                entry.credit_cents,
                                                        ),
                                                        currency,
                                                    )}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <StatusBadge
                                                value={entry.display_status}
                                            />
                                        </td>
                                        <td className="px-4 py-2.5 text-xs whitespace-nowrap">
                                            {entry.cheque_number ?? "—"}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    <Pagination links={pagination} />
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}
