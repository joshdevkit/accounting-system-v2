import { Head, Link, router } from "@inertiajs/react";
import { Check, ChevronsUpDown } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCents, formatCentsSigned, formatDate } from "@/lib/format";
import { compactQuery } from "@/lib/utils";
import type { AccountCategory } from "@/types/models";
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

type Props = {
    company: { name: string; code: string; currency: string };
    account: {
        id: number;
        code: string;
        name: string;
        description: string | null;
        category: AccountCategory | null;
        normal_balance: "debit" | "credit" | null;
        children: number;
    };
    ledger: {
        lines: {
            id: number;
            journal_entry_id: number;
            date: string | null;
            reference_no: string | null;
            status: string | null;
            source_type: string | null;
            branch: string | null;
            vendor: string | null;
            entry_description: string | null;
            description: string | null;
            account_code: string | null;
            account_name: string | null;
            debit: number;
            credit: number;
            balance: number;
        }[];
        debit_cents: number;
        credit_cents: number;
        balance_cents: number;
    };
    filters: Record<string, unknown> & {
        include_sub_accounts?: boolean;
        from?: string | null;
        to?: string | null;
        branch_id?: number | null;
        fiscal_year_id?: number | null;
        accounting_period_id?: number | null;
    };
    accounts: { id: number; label: string }[];
    pickers: {
        branches: { id: number; name: string; code: string }[];
        fiscalYears: {
            id: number;
            year: number;
            periods: { id: number; name: string; period_number: number }[];
        }[];
    };
};

export default function LedgerReport({
    company,
    account,
    ledger,
    filters,
    accounts,
    pickers,
}: Props) {
    const [accountId, setAccountId] = useState(String(account.id));

    const apply = (next: Record<string, unknown>) => {
        router.get(
            "/reports/ledger",
            compactQuery({
                account: accountId,
                fiscal_year_id: filters.fiscal_year_id,
                accounting_period_id: filters.accounting_period_id,
                branch_id: filters.branch_id,
                from: filters.from,
                to: filters.to,
                sub_accounts: filters.include_sub_accounts ? "1" : "0",
                ...next,
            }),
            { preserveState: true, preserveScroll: true },
        );
    };

    const selectedYear = pickers.fiscalYears.find(
        (year) => year.id === filters.fiscal_year_id,
    );

    return (
        <AuthenticatedLayout
            title={`${account.code} · ${account.name}`}
            description="Every posting to this account, oldest first, with a running balance"
        >
            <Head title={`${account.code} ${account.name}`} />
            <div className="space-y-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="ml-0 flex flex-wrap items-end gap-2">
                        <div className="space-y-1">
                            <label className="text-muted-foreground text-xs">
                                Account
                            </label>

                            <Popover>
                                <PopoverTrigger
                                    type="button"
                                    className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-56 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none"
                                >
                                    <span className="truncate">
                                        {accounts.find(
                                            (option) =>
                                                String(option.id) === accountId,
                                        )?.label ?? "Select account"}
                                    </span>

                                    <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                                </PopoverTrigger>

                                <PopoverContent className="w-[320px] p-0">
                                    <Command>
                                        <CommandInput placeholder="Search account..." />

                                        <CommandList>
                                            <CommandEmpty>
                                                No account found.
                                            </CommandEmpty>

                                            <CommandGroup>
                                                {accounts.map((option) => (
                                                    <CommandItem
                                                        key={option.id}
                                                        value={`${option.label} ${option.id}`}
                                                        onSelect={() => {
                                                            setAccountId(
                                                                String(
                                                                    option.id,
                                                                ),
                                                            );
                                                        }}
                                                    >
                                                        <Check
                                                            className={`mr-2 size-4 ${
                                                                accountId ===
                                                                String(
                                                                    option.id,
                                                                )
                                                                    ? "opacity-100"
                                                                    : "opacity-0"
                                                            }`}
                                                        />

                                                        <span className="truncate">
                                                            {option.label}
                                                        </span>
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <Button
                            size="sm"
                            type="button"
                            onClick={() => apply({})}
                        >
                            Show
                        </Button>

                        <div className="space-y-1">
                            <label className="text-muted-foreground text-xs">
                                Fiscal year
                            </label>

                            <Popover>
                                <PopoverTrigger
                                    type="button"
                                    className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-32 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none"
                                >
                                    <span className="truncate">
                                        {filters.fiscal_year_id
                                            ? (pickers.fiscalYears.find(
                                                  (year) =>
                                                      year.id ===
                                                      filters.fiscal_year_id,
                                              )?.year ?? "Select year")
                                            : "All years"}
                                    </span>

                                    <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                                </PopoverTrigger>

                                <PopoverContent className="w-[220px] p-0">
                                    <Command>
                                        <CommandInput placeholder="Search year..." />

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
                                                            value={String(
                                                                year.year,
                                                            )}
                                                            onSelect={() =>
                                                                apply({
                                                                    fiscal_year_id:
                                                                        year.id,
                                                                    accounting_period_id:
                                                                        null,
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
                                                            {year.year}
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
                            <label className="text-muted-foreground text-xs">
                                Period
                            </label>

                            <Popover>
                                <PopoverTrigger
                                    type="button"
                                    disabled={!selectedYear}
                                    className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-36 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none disabled:pointer-events-none disabled:opacity-50"
                                >
                                    <span className="truncate">
                                        {filters.accounting_period_id
                                            ? (selectedYear?.periods.find(
                                                  (period) =>
                                                      period.id ===
                                                      filters.accounting_period_id,
                                              )?.name ?? "Select period")
                                            : "All periods"}
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

                                <PopoverContent className="w-[260px] p-0">
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
                                                            value={`${branch.name} ${branch.code}`}
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

                        <label className="text-muted-foreground flex h-9 items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                className="accent-primary size-4"
                                checked={!!filters.include_sub_accounts}
                                onChange={(event) =>
                                    apply({
                                        sub_accounts: event.target.checked
                                            ? "1"
                                            : "0",
                                    })
                                }
                            />
                            Include sub-accounts
                        </label>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        {
                            label: "Total debits",
                            value: formatCents(
                                ledger.debit_cents,
                                company.currency,
                            ),
                        },
                        {
                            label: "Total credits",
                            value: formatCents(
                                ledger.credit_cents,
                                company.currency,
                            ),
                        },
                        {
                            label: `${account.name} balance`,
                            value: formatCentsSigned(
                                Math.abs(ledger.balance_cents),
                                company.currency,
                            ),
                        },
                        {
                            label: "Postings",
                            value: String(ledger.lines.length),
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

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Reference
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Vendor
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Description
                                    </th>
                                    
                                    <th className="px-4 py-2 font-medium">
                                        Branch
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
                                {ledger.lines.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="text-muted-foreground px-4 py-12 text-center text-sm"
                                        >
                                            No posted entries on this account
                                            for the selected range.
                                        </td>
                                    </tr>
                                )}
                                {ledger.lines.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="hover:bg-muted/30 border-b last:border-0"
                                    >
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            {formatDate(line.date)}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Link
                                                href={`/journal-entries/${line.journal_entry_id}`}
                                                className="font-mono text-xs hover:underline"
                                            >
                                                {line.reference_no}
                                            </Link>
                                            {line.account_code &&
                                                line.account_code !==
                                                    account.code && (
                                                    <Badge
                                                        variant="outline"
                                                        className="ml-2"
                                                    >
                                                        {line.account_code}
                                                    </Badge>
                                                )}
                                        </td>
                                        <td className="px-4 py-2">
                                            {line.vendor || "—"}
                                        </td>
                                        <td className="max-w-md px-4 py-2">
                                            <span className="line-clamp-1">
                                                {line.description ??
                                                    line.entry_description ??
                                                    "—"}
                                            </span>
                                            {line.status === "draft" && (
                                                <StatusBadge
                                                    value="draft"
                                                    className="ml-2 h-4 px-1.5 text-[10px]"
                                                />
                                            )}
                                            {line.source_type === "pos" && (
                                                <StatusBadge
                                                    value="pos"
                                                    className="ml-2 h-4 px-1.5 text-[10px]"
                                                />
                                            )}
                                        </td>
                                        
                                        <td className="text-muted-foreground px-4 py-2">
                                            {line.branch ?? "—"}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {line.debit
                                                ? formatCents(
                                                      line.debit,
                                                      company.currency,
                                                  )
                                                : "—"}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {line.credit
                                                ? formatCents(
                                                      line.credit,
                                                      company.currency,
                                                  )
                                                : "—"}
                                        </td>
                                        <td className="px-4 py-2 text-right font-medium tabular-nums">
                                            {formatCentsSigned(
                                                line.balance,
                                                company.currency,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <p className="text-muted-foreground text-xs">
                    Running balance is signed to the account's normal{" "}
                    {account.normal_balance ?? "debit"} side
                    {account.children > 0
                        ? " · this is a summary account, tick “include sub-accounts” to see the detail"
                        : ""}
                    .
                </p>
            </div>
        </AuthenticatedLayout>
    );
}