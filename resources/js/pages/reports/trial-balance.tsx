import { Head, Link, router } from "@inertiajs/react";
import {
    Download,
    FileSpreadsheet,
    Printer,
    TriangleAlert,
} from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Check, ChevronsUpDown } from "lucide-react";
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
import { formatCents, formatCentsSigned, formatDate } from "@/lib/format";
import { compactQuery } from "@/lib/utils";

type Row = {
    id: number;
    code: string;
    name: string;
    category: string | null;
    normal_balance: "debit" | "credit";
    depth: number;
    is_summary: boolean;
    is_postable: boolean;
    own_debit: number;
    own_credit: number;
    debit: number;
    credit: number;
    balance: number;
};

type Props = {
    company: { name: string; code: string; currency: string };
    generatedAt: string;
    rows: Row[];
    totals: { debits: number; credits: number; difference: number };
    filters: Record<string, unknown> & {
        branch_id?: number | null;
        fiscal_year_id?: number | null;
        accounting_period_id?: number | null;
        from?: string | null;
        to?: string | null;
        summary?: boolean;
    };
    pickers: {
        branches: { id: number; name: string; code: string }[];
        fiscalYears: {
            id: number;
            year: number;
            status: string;
            start_date: string;
            end_date: string;
            periods: {
                id: number;
                name: string;
                period_number: number;
                start_date: string;
                end_date: string;
                status: string;
            }[];
        }[];
    };
    unbalanced: boolean;
};

export default function TrialBalanceReport({
    company,
    generatedAt,
    rows,
    totals,
    filters,
    pickers,
    unbalanced,
}: Props) {
    const [onlyWithActivity, setOnlyWithActivity] = useState(false);

    const selectedYear = pickers.fiscalYears.find(
        (year) => year.id === filters.fiscal_year_id,
    );

    const apply = (next: Record<string, unknown>) => {
        router.get(
            "/reports/trial-balance",
            compactQuery({ ...filters, ...next }),
            { preserveState: true, preserveScroll: true },
        );
    };

    const visible = onlyWithActivity
        ? rows.filter((row) => row.debit !== 0 || row.credit !== 0)
        : rows;

    return (
        <AuthenticatedLayout
            title="Trial balance"
            description="Every account and its posted balance, straight from the journal"
            actions={
                <>
                    <a
                        href={`/reports/trial-balance/pdf?${new URLSearchParams(
                            {
                                ...(filters as Record<string, string>),
                                only_with_activity: onlyWithActivity
                                    ? "1"
                                    : "0",
                            },
                        ).toString()}`}
                    >
                        <Button size="sm" variant="outline">
                            <Download className="size-4" />
                            Download PDF
                        </Button>
                    </a>
                    <Link href="/journal-entries">
                        <Button size="sm" variant="outline">
                            <FileSpreadsheet className="size-4" />
                            Journal
                        </Button>
                    </Link>
                </>
            }
        >
            <Head title="Trial balance" />

            <div className="space-y-4">
                <Card>
                    <CardContent className="flex flex-wrap items-end gap-3 p-3">
                        {/* Fiscal year */}
                        <div className="space-y-1">
                            <label className="text-muted-foreground text-xs">
                                Fiscal year
                            </label>

                            <Popover>
                                <PopoverTrigger
                                    type="button"
                                    className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-36 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none"
                                >
                                    <span className="truncate">
                                        {filters.fiscal_year_id
                                            ? pickers.fiscalYears.find(
                                                  (year) =>
                                                      year.id ===
                                                      filters.fiscal_year_id,
                                              )
                                                ? `${
                                                      pickers.fiscalYears.find(
                                                          (year) =>
                                                              year.id ===
                                                              filters.fiscal_year_id,
                                                      )?.year
                                                  } (${
                                                      pickers.fiscalYears.find(
                                                          (year) =>
                                                              year.id ===
                                                              filters.fiscal_year_id,
                                                      )?.status
                                                  })`
                                                : "Select year"
                                            : "All years"}
                                    </span>

                                    <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                                </PopoverTrigger>

                                <PopoverContent className="w-[240px] p-0">
                                    <Command>
                                        <CommandInput placeholder="Search fiscal year..." />

                                        <CommandList>
                                            <CommandEmpty>
                                                No fiscal year found.
                                            </CommandEmpty>

                                            <CommandGroup>
                                                <CommandItem
                                                    value="all years"
                                                    onSelect={() =>
                                                        apply({
                                                            fiscal_year_id:
                                                                null,
                                                            accounting_period_id:
                                                                null,
                                                            from: null,
                                                            to: null,
                                                        })
                                                    }
                                                >
                                                    <Check
                                                        className={`mr-2 size-4 ${
                                                            !filters.fiscal_year_id
                                                                ? "opacity-100"
                                                                : "opacity-0"
                                                        }`}
                                                    />
                                                    All years
                                                </CommandItem>

                                                {pickers.fiscalYears.map(
                                                    (year) => (
                                                        <CommandItem
                                                            key={year.id}
                                                            value={`${year.year} ${year.status}`}
                                                            onSelect={() =>
                                                                apply({
                                                                    fiscal_year_id:
                                                                        year.id,
                                                                    accounting_period_id:
                                                                        null,
                                                                    from: year.start_date,
                                                                    to: year.end_date,
                                                                })
                                                            }
                                                        >
                                                            <Check
                                                                className={`mr-2 size-4 ${
                                                                    filters.fiscal_year_id ===
                                                                    year.id
                                                                        ? "opacity-100"
                                                                        : "opacity-0"
                                                                }`}
                                                            />
                                                            {year.year} (
                                                            {year.status})
                                                        </CommandItem>
                                                    ),
                                                )}
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        {/* Period */}
                        <div className="space-y-1">
                            <label className="text-muted-foreground text-xs">
                                Period
                            </label>

                            <Popover>
                                <PopoverTrigger
                                    type="button"
                                    disabled={!selectedYear}
                                    className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-40 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none disabled:pointer-events-none disabled:opacity-50"
                                >
                                    <span className="truncate">
                                        {filters.accounting_period_id
                                            ? selectedYear?.periods.find(
                                                  (period) =>
                                                      period.id ===
                                                      filters.accounting_period_id,
                                              )
                                                ? `${
                                                      selectedYear.periods.find(
                                                          (period) =>
                                                              period.id ===
                                                              filters.accounting_period_id,
                                                      )?.period_number
                                                  }. ${
                                                      selectedYear.periods.find(
                                                          (period) =>
                                                              period.id ===
                                                              filters.accounting_period_id,
                                                      )?.name
                                                  }`
                                                : "Select period"
                                            : selectedYear
                                              ? "All periods"
                                              : "Pick a year first"}
                                    </span>

                                    <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                                </PopoverTrigger>

                                <PopoverContent className="w-[280px] p-0">
                                    <Command>
                                        <CommandInput placeholder="Search period..." />

                                        <CommandList>
                                            <CommandEmpty>
                                                No period found.
                                            </CommandEmpty>

                                            <CommandGroup>
                                                <CommandItem
                                                    value="all periods"
                                                    onSelect={() =>
                                                        apply({
                                                            accounting_period_id:
                                                                null,
                                                            from:
                                                                selectedYear?.start_date ??
                                                                filters.from,
                                                            to:
                                                                selectedYear?.end_date ??
                                                                filters.to,
                                                        })
                                                    }
                                                >
                                                    <Check
                                                        className={`mr-2 size-4 ${
                                                            !filters.accounting_period_id
                                                                ? "opacity-100"
                                                                : "opacity-0"
                                                        }`}
                                                    />
                                                    All periods
                                                </CommandItem>

                                                {selectedYear?.periods.map(
                                                    (period) => (
                                                        <CommandItem
                                                            key={period.id}
                                                            value={`${period.period_number} ${period.name}`}
                                                            onSelect={() =>
                                                                apply({
                                                                    accounting_period_id:
                                                                        period.id,
                                                                    from: period.start_date,
                                                                    to: period.end_date,
                                                                })
                                                            }
                                                        >
                                                            <Check
                                                                className={`mr-2 size-4 ${
                                                                    filters.accounting_period_id ===
                                                                    period.id
                                                                        ? "opacity-100"
                                                                        : "opacity-0"
                                                                }`}
                                                            />
                                                            {
                                                                period.period_number
                                                            }
                                                            . {period.name}
                                                        </CommandItem>
                                                    ),
                                                )}
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        {/* Branch */}
                        <div className="space-y-1">
                            <label className="text-muted-foreground text-xs">
                                Branch
                            </label>

                            <Popover>
                                <PopoverTrigger
                                    type="button"
                                    className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-32 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none"
                                >
                                    <span className="truncate">
                                        {filters.branch_id
                                            ? (pickers.branches.find(
                                                  (branch) =>
                                                      branch.id ===
                                                      filters.branch_id,
                                              )?.name ?? "Select branch")
                                            : "All branches"}
                                    </span>

                                    <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                                </PopoverTrigger>

                                <PopoverContent className="w-[280px] p-0">
                                    <Command>
                                        <CommandInput placeholder="Search branch..." />

                                        <CommandList>
                                            <CommandEmpty>
                                                No branch found.
                                            </CommandEmpty>

                                            <CommandGroup>
                                                <CommandItem
                                                    value="all branches"
                                                    onSelect={() =>
                                                        apply({
                                                            branch_id: null,
                                                        })
                                                    }
                                                >
                                                    <Check
                                                        className={`mr-2 size-4 ${
                                                            !filters.branch_id
                                                                ? "opacity-100"
                                                                : "opacity-0"
                                                        }`}
                                                    />
                                                    All branches
                                                </CommandItem>

                                                {pickers.branches.map(
                                                    (branch) => (
                                                        <CommandItem
                                                            key={branch.id}
                                                            value={`${branch.name} ${branch.code ?? ""}`}
                                                            onSelect={() =>
                                                                apply({
                                                                    branch_id:
                                                                        branch.id,
                                                                })
                                                            }
                                                        >
                                                            <Check
                                                                className={`mr-2 size-4 ${
                                                                    filters.branch_id ===
                                                                    branch.id
                                                                        ? "opacity-100"
                                                                        : "opacity-0"
                                                                }`}
                                                            />

                                                            <div className="flex min-w-0 flex-col">
                                                                <span className="truncate">
                                                                    {
                                                                        branch.name
                                                                    }
                                                                </span>

                                                                {branch.code && (
                                                                    <span className="text-muted-foreground text-xs">
                                                                        {
                                                                            branch.code
                                                                        }
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </CommandItem>
                                                    ),
                                                )}
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="from"
                                className="text-muted-foreground text-xs"
                            >
                                From
                            </label>
                            <Input
                                id="from"
                                type="date"
                                className="w-auto"
                                defaultValue={filters.from ?? ""}
                                onBlur={(event) =>
                                    apply({ from: event.target.value || null })
                                }
                            />
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="to"
                                className="text-muted-foreground text-xs"
                            >
                                To
                            </label>
                            <Input
                                id="to"
                                type="date"
                                className="w-auto"
                                defaultValue={filters.to ?? ""}
                                onBlur={(event) =>
                                    apply({ to: event.target.value || null })
                                }
                            />
                        </div>

                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.get(
                                    "/reports/trial-balance",
                                    {},
                                    {
                                        preserveScroll: true,
                                    },
                                )
                            }
                        >
                            Reset
                        </Button>

                        <label className="text-muted-foreground ml-auto flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={onlyWithActivity}
                                onChange={(event) =>
                                    setOnlyWithActivity(event.target.checked)
                                }
                                className="accent-primary size-4"
                            />
                            Only accounts with balances
                        </label>
                    </CardContent>
                </Card>

                {unbalanced && (
                    <Alert variant="destructive">
                        <TriangleAlert className="size-4" />
                        <AlertTitle>Trial balance is out of balance</AlertTitle>
                        <AlertDescription>
                            Debits and credits differ by{" "}
                            {formatCents(
                                Math.abs(totals.difference),
                                company.currency,
                            )}
                            . Unposted drafts or a manual ledger adjustment is
                            usually the cause.
                        </AlertDescription>
                    </Alert>
                )}

                <Card id="printable-trial-balance">
                    <CardContent className="p-0">
                        <div className="border-b px-4 py-4">
                            <p className="text-sm font-semibold">
                                {company.name}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                Trial balance ·{" "}
                                {filters.from
                                    ? `${formatDate(filters.from)} – ${formatDate(filters.to)}`
                                    : "All posted entries"}
                                {selectedYear
                                    ? ` · FY ${selectedYear.year}`
                                    : ""}{" "}
                                · generated {formatDate(generatedAt)}
                            </p>
                        </div>

                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">
                                        Account
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Debit
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Credit
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Balance
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="text-muted-foreground px-4 py-12 text-center text-sm"
                                        >
                                            Nothing has been posted for this
                                            range yet.
                                        </td>
                                    </tr>
                                )}
                                {visible.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={
                                            row.is_summary
                                                ? "bg-muted/30 border-b font-medium last:border-0"
                                                : "hover:bg-muted/30 border-b last:border-0"
                                        }
                                    >
                                        <td className="px-4 py-2">
                                            <div
                                                className="flex items-center gap-2"
                                                style={{
                                                    paddingLeft: `${row.depth * 1.25}rem`,
                                                }}
                                            >
                                                <span className="text-muted-foreground font-mono text-xs tabular-nums">
                                                    {row.code}
                                                </span>
                                                <span>{row.name}</span>
                                                {row.is_summary && (
                                                    <Badge
                                                        variant="outline"
                                                        className="print:hidden"
                                                    >
                                                        summary
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {row.debit
                                                ? formatCents(
                                                      row.debit,
                                                      company.currency,
                                                  )
                                                : "—"}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {row.credit
                                                ? formatCents(
                                                      row.credit,
                                                      company.currency,
                                                  )
                                                : "—"}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {row.is_summary ? (
                                                "—"
                                            ) : (
                                                <Link
                                                    href={`/reports/ledger?account=${row.id}${
                                                        filters.fiscal_year_id
                                                            ? `&fiscal_year_id=${filters.fiscal_year_id}`
                                                            : ""
                                                    }${
                                                        filters.accounting_period_id
                                                            ? `&accounting_period_id=${filters.accounting_period_id}`
                                                            : ""
                                                    }${
                                                        filters.branch_id
                                                            ? `&branch_id=${filters.branch_id}`
                                                            : ""
                                                    }${filters.from ? `&from=${filters.from}` : ""}${
                                                        filters.to
                                                            ? `&to=${filters.to}`
                                                            : ""
                                                    }`}
                                                    className="hover:underline"
                                                >
                                                    {formatCentsSigned(
                                                        row.balance,
                                                        company.currency,
                                                    )}
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 font-semibold">
                                    <td className="px-4 py-3">Totals</td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatCents(
                                            totals.debits,
                                            company.currency,
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatCents(
                                            totals.credits,
                                            company.currency,
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {/* {totals.difference === 0 ? (
                                            <Badge>balanced</Badge>
                                        ) : (
                                            <Badge variant="destructive">
                                                out by{' '}
                                                {formatCents(
                                                    Math.abs(totals.difference),
                                                    company.currency,
                                                )}
                                            </Badge>
                                        )} */}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </CardContent>
                </Card>

                <p className="text-muted-foreground text-xs">
                    Summary accounts roll up their sub-accounts, so only the
                    leaf balances are counted once. Draft entries are excluded
                    by design.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
