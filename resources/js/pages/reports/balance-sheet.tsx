import { Head, router } from "@inertiajs/react";
import {
    Download,
    FileSpreadsheet,
    Printer,
    TriangleAlert,
} from "lucide-react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
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
import { Link } from "@inertiajs/react";
import { formatCents, formatDate } from "@/lib/format";

type Row = { id: number; code: string; name: string; amount_cents: number };

type Props = {
    company: { name: string; code: string; currency: string };
    generatedAt: string;
    asOf: string;
    assets: Row[];
    liabilities: Row[];
    equity: Row[];
    totals: { assets: number; liabilities: number; equity: number };
    isBalanced: boolean;
    filters: { branch_id: number | null; as_of: string };
    branches: { id: number; name: string; code: string }[];
};

export default function BalanceSheetReport({
    company,
    generatedAt,
    asOf,
    assets,
    liabilities,
    equity,
    totals,
    isBalanced,
    filters,
    branches,
}: Props) {
    const apply = (next: Record<string, unknown>) => {
        router.get(
            "/reports/balance-sheet",
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true },
        );
    };

    const liabilitiesPlusEquity = totals.liabilities + totals.equity;

    return (
        <AuthenticatedLayout
            title="Balance sheet"
            description="What the company owns, owes, and has left over, as of a date"
            actions={
                <>
                    <a
                        href={`/reports/balance-sheet/pdf?${new URLSearchParams(
                            {
                                as_of: filters.as_of,
                                ...(filters.branch_id
                                    ? { branch_id: String(filters.branch_id) }
                                    : {}),
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
            <Head title="Balance sheet" />

            <div className="space-y-4">
                <Card>
                    <CardContent className="flex flex-wrap items-end gap-3 p-3">
                        <div className="space-y-1">
                            <label
                                htmlFor="as_of"
                                className="text-muted-foreground text-xs"
                            >
                                As of
                            </label>
                            <Input
                                id="as_of"
                                type="date"
                                className="w-auto"
                                defaultValue={filters.as_of}
                                onBlur={(event) =>
                                    apply({ as_of: event.target.value })
                                }
                            />
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="branch_id"
                                className="text-muted-foreground text-xs"
                            >
                                Branch
                            </label>
                            <Popover>
                                <PopoverTrigger
                                    type="button"
                                    className="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-auto min-w-32 items-center justify-between gap-2 rounded-md border px-3 text-sm shadow-xs outline-none"
                                >
                                    <span className="truncate">
                                        {filters.branch_id
                                            ? (branches.find(
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

                                                {branches.map((branch) => (
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

                                                        <span className="truncate">
                                                            {branch.name}
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
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.get(
                                    "/reports/balance-sheet",
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Reset
                        </Button>
                    </CardContent>
                </Card>

                {!isBalanced && (
                    <Alert variant="destructive">
                        <TriangleAlert className="size-4" />
                        <AlertTitle>Balance sheet does not balance</AlertTitle>
                        <AlertDescription>
                            Assets (
                            {formatCents(totals.assets, company.currency)})
                            don't equal Liabilities + Equity (
                            {formatCents(
                                liabilitiesPlusEquity,
                                company.currency,
                            )}
                            ). This usually means an unbalanced posting slipped
                            through — check recent journal entries.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardContent className="p-0">
                        <div className="border-b px-4 py-4">
                            <p className="text-sm font-semibold">
                                {company.name}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                Balance sheet · as of {formatDate(asOf)} ·
                                generated {formatDate(generatedAt)}
                            </p>
                        </div>

                        <Section
                            title="Assets"
                            rows={assets}
                            currency={company.currency}
                            total={totals.assets}
                        />
                        <Section
                            title="Liabilities"
                            rows={liabilities}
                            currency={company.currency}
                            total={totals.liabilities}
                        />
                        <Section
                            title="Equity"
                            rows={equity}
                            currency={company.currency}
                            total={totals.equity}
                        />

                        <div className="flex items-center justify-between border-t-2 px-4 py-3 font-semibold">
                            <span>Total Liabilities + Equity</span>
                            <span className="tabular-nums">
                                {formatCents(
                                    liabilitiesPlusEquity,
                                    company.currency,
                                )}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <p className="text-muted-foreground text-xs">
                    A snapshot as of the selected date — every posted
                    transaction up to that day is included. "Current earnings"
                    under Equity is net income calculated live from all revenue
                    and expenses to date.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}

function Section({
    title,
    rows,
    currency,
    total,
}: {
    title: string;
    rows: Row[];
    currency: string;
    total: number;
}) {
    return (
        <div className="border-b last:border-0">
            <div className="bg-muted/30 px-4 py-2 text-sm font-semibold">
                {title}
            </div>
            <table className="w-full text-sm">
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td
                                className="text-muted-foreground px-4 py-6 text-center text-xs"
                                colSpan={2}
                            >
                                No {title.toLowerCase()} as of this date.
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            className="hover:bg-muted/20 border-b last:border-0"
                        >
                            <td className="px-4 py-2">
                                {row.code && (
                                    <span className="text-muted-foreground font-mono text-xs">
                                        {row.code}
                                    </span>
                                )}{" "}
                                {row.name}
                            </td>
                            <td className="px-4 py-2 text-right tabular-nums">
                                {formatCents(row.amount_cents, currency)}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t font-medium">
                        <td className="px-4 py-2">
                            Total {title.toLowerCase()}
                        </td>
                        <td className="px-4 py-2 text-right tabular-nums">
                            {formatCents(total, currency)}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
