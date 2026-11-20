import { Head, Link } from "@inertiajs/react";
import { Download, FileSpreadsheet, Printer } from "lucide-react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ReportFilterBar } from "@/components/reports/report-filter-bar";
import { formatCents, formatCentsSigned, formatDate } from "@/lib/format";
import type {
    ReportCompany,
    ReportFilters,
    ReportPickers,
} from "@/types/reports";

type Row = { id: number; code: string; name: string; amount_cents: number };

type Props = {
    company: ReportCompany;
    generatedAt: string;
    revenue: Row[];
    expenses: Row[];
    totals: { revenue: number; expenses: number; net_income: number };
    previous: {
        revenue: Row[];
        expenses: Row[];
        totals: { revenue: number; expenses: number; net_income: number };
        range: { from: string; to: string };
    } | null;
    compare: boolean;
    filters: ReportFilters;
    pickers: ReportPickers;
};

import { router } from "@inertiajs/react";
import { compactQuery } from "@/lib/utils";

export default function IncomeStatementReport({
    company,
    generatedAt,
    revenue,
    expenses,
    totals,
    previous,
    compare,
    filters,
    pickers,
}: Props) {
    const isProfit = totals.net_income >= 0;

    const toggleCompare = (checked: boolean) => {
        router.get(
            "/reports/income-statement",
            compactQuery({ ...filters, compare: checked ? 1 : null }),
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            title="Income statement"
            description="Revenue and expenses for the period, netting to income or loss"
            actions={
                <>
                    <a
                        href={`/reports/income-statement/pdf?${new URLSearchParams(filters as Record<string, string>).toString()}`}
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
            <Head title="Income statement" />

            <div className="space-y-4">
                <ReportFilterBar
                    routeName="/reports/income-statement"
                    filters={filters}
                    pickers={pickers}
                    extra={
                        <label className="text-muted-foreground flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={compare}
                                onChange={(event) =>
                                    toggleCompare(event.target.checked)
                                }
                                disabled={!filters.from || !filters.to}
                                className="accent-primary size-4"
                            />
                            Compare to previous period
                        </label>
                    }
                />

                {compare && (!filters.from || !filters.to) && (
                    <p className="text-muted-foreground text-xs">
                        Pick a From and To date to enable comparison — there's
                        no natural "previous period" for an unbounded range.
                    </p>
                )}

                <Card>
                    <CardContent className="p-0">
                        <div className="border-b px-4 py-4">
                            <p className="text-sm font-semibold">
                                {company.name}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                Income statement ·{" "}
                                {filters.from
                                    ? `${formatDate(filters.from)} – ${formatDate(filters.to)}`
                                    : "All posted entries"}{" "}
                                · generated {formatDate(generatedAt)}
                                {previous && (
                                    <>
                                        {" "}
                                        vs {formatDate(
                                            previous.range.from,
                                        )} – {formatDate(previous.range.to)}
                                    </>
                                )}
                            </p>
                        </div>

                        <ComparisonSection
                            title="Revenue"
                            currentRows={revenue}
                            previousRows={previous?.revenue ?? null}
                            currency={company.currency}
                            currentTotal={totals.revenue}
                            previousTotal={previous?.totals.revenue ?? null}
                            totalRevenue={totals.revenue}
                        />
                        <ComparisonSection
                            title="Expenses"
                            currentRows={expenses}
                            previousRows={previous?.expenses ?? null}
                            currency={company.currency}
                            currentTotal={totals.expenses}
                            previousTotal={previous?.totals.expenses ?? null}
                            totalRevenue={totals.revenue}
                        />

                        <div className="flex items-center justify-between border-t-2 px-4 py-3 font-semibold">
                            <span>{isProfit ? "Net income" : "Net loss"}</span>
                            <div className="flex items-center gap-6">
                                {previous && (
                                    <span className="text-muted-foreground text-xs font-normal tabular-nums">
                                        Previous:{" "}
                                        {formatCentsSigned(
                                            previous.totals.net_income,
                                            company.currency,
                                        )}
                                    </span>
                                )}
                                <span className="tabular-nums">
                                    {formatCentsSigned(
                                        totals.net_income,
                                        company.currency,
                                    )}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <p className="text-muted-foreground text-xs">
                    Only revenue and expense accounts appear here. Draft entries
                    are excluded by design.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}

function ComparisonSection({
    title,
    currentRows,
    previousRows,
    currency,
    currentTotal,
    previousTotal,
    totalRevenue,
}: {
    title: string;
    currentRows: Row[];
    previousRows: Row[] | null;
    currency: string;
    currentTotal: number;
    previousTotal: number | null;
    totalRevenue: number;
}) {
    const percentOf = (amount: number) =>
        totalRevenue > 0
            ? `${((amount / totalRevenue) * 100).toFixed(1)}%`
            : "—";

    // Merge current and previous rows by account code, so an account that
    // only had activity in one period still gets a row (with 0 on the other side).
    const codes = new Set([
        ...currentRows.map((row) => row.code),
        ...(previousRows ?? []).map((row) => row.code),
    ]);

    const merged = Array.from(codes)
        .sort()
        .map((code) => {
            const current = currentRows.find((row) => row.code === code);
            const prior = previousRows?.find((row) => row.code === code);

            return {
                code,
                name: current?.name ?? prior?.name ?? code,
                current: current?.amount_cents ?? 0,
                previous: prior?.amount_cents ?? 0,
            };
        });

    return (
        <div className="border-b last:border-0">
            <div className="bg-muted/30 px-4 py-2 text-sm font-semibold">
                {title}
            </div>
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-muted-foreground text-left text-xs">
                        <th className="px-4 py-1 font-medium">Account</th>
                        <th className="px-4 py-1 text-right font-medium">
                            Amount
                        </th>
                        {previousRows && (
                            <th className="px-4 py-1 text-right font-medium">
                                Previous
                            </th>
                        )}
                        {previousRows && (
                            <th className="px-4 py-1 text-right font-medium">
                                Change
                            </th>
                        )}
                        <th className="w-20 px-4 py-1 text-right font-medium">
                            % of revenue
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {merged.length === 0 && (
                        <tr>
                            <td
                                className="text-muted-foreground px-4 py-6 text-center text-xs"
                                colSpan={5}
                            >
                                No {title.toLowerCase()} posted for this range.
                            </td>
                        </tr>
                    )}
                    {merged.map((row) => {
                        const change = row.current - row.previous;

                        return (
                            <tr
                                key={row.code}
                                className="hover:bg-muted/20 border-b last:border-0"
                            >
                                <td className="px-4 py-2">
                                    <span className="text-muted-foreground font-mono text-xs">
                                        {row.code}
                                    </span>{" "}
                                    {row.name}
                                </td>
                                <td className="px-4 py-2 text-right tabular-nums">
                                    {formatCents(row.current, currency)}
                                </td>
                                {previousRows && (
                                    <td className="text-muted-foreground px-4 py-2 text-right tabular-nums">
                                        {formatCents(row.previous, currency)}
                                    </td>
                                )}
                                {previousRows && (
                                    <td
                                        className={
                                            change === 0
                                                ? "text-muted-foreground px-4 py-2 text-right tabular-nums"
                                                : change > 0
                                                  ? "px-4 py-2 text-right tabular-nums text-emerald-600"
                                                  : "px-4 py-2 text-right tabular-nums text-destructive"
                                        }
                                    >
                                        {formatCentsSigned(change, currency)}
                                    </td>
                                )}
                                <td className="text-muted-foreground px-4 py-2 text-right tabular-nums">
                                    {percentOf(row.current)}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
                <tfoot>
                    <tr className="border-t font-medium">
                        <td className="px-4 py-2">
                            Total {title.toLowerCase()}
                        </td>
                        <td className="px-4 py-2 text-right tabular-nums">
                            {formatCents(currentTotal, currency)}
                        </td>
                        {previousRows && previousTotal !== null && (
                            <td className="text-muted-foreground px-4 py-2 text-right tabular-nums">
                                {formatCents(previousTotal, currency)}
                            </td>
                        )}
                        {previousRows && previousTotal !== null && (
                            <td className="px-4 py-2 text-right tabular-nums">
                                {formatCentsSigned(
                                    currentTotal - previousTotal,
                                    currency,
                                )}
                            </td>
                        )}
                        <td className="text-muted-foreground px-4 py-2 text-right tabular-nums">
                            {percentOf(currentTotal)}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

