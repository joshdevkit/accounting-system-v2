import { Head, Link, router } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowUpRight,
    BanknoteArrowDown,
    CalendarClock,
    Eye,
    FileClock,
    Lock,
    Plus,
    Receipt,
    TriangleAlert,
    Unlock,
    Wallet,
} from "lucide-react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCents, formatDate } from "@/lib/format";
import { cn } from "@/lib/utils";
import { CashFlowChart } from "@/components/dashboard/cash-flow-chart";
import { IncomeExpenseChart } from "@/components/dashboard/income-expense-chart";
import journalEntries, { create } from "@/routes/journal-entries";
import posEntries, { create as Poscreate } from "@/routes/pos-entries";
import reports, { trialBalance } from "@/routes/reports";
import accounts from "@/routes/accounts";
import { dashboard } from "@/routes";
import { AgingCard } from "@/components/dashboard/aging-card";
import { SimpleSelect } from "@/components/ui/simple-select";
import { Props } from "@/types/dashboard";

const KPI_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    cash: Wallet,
    net_sales: Receipt,
    receivables: ArrowUpRight,
    unposted: FileClock,
};

export default function Dashboard({
    company,
    businessDate,
    branchId,
    branches,
    fiscalYear,
    period,
    kpis,
    tenders,
    posSummaries,
    recentEntries,
    cashFlow,
    incomeExpense,
    alerts,
    receivables,
    payables,
}: Props) {
    const tenderTotal = tenders.reduce(
        (carry, tender) => carry + tender.cents,
        0,
    );
    const maxTender = tenders.reduce(
        (carry, tender) => Math.max(carry, tender.cents),
        0,
    );

    return (
        <AuthenticatedLayout
            title={
                branchId
                    ? `Dashboard · ${
                          branches.find((branch) => branch.id === branchId)
                              ?.name ?? "branch"
                      }`
                    : "Dashboard"
            }
            description={`Today's overview for ${company.name} · ${formatDate(businessDate)}`}
            actions={
                <>
                    <div className="space-y-1">
                        <label
                            htmlFor="branch_id"
                            className="sr-only"
                            aria-hidden
                        >
                            Branch
                        </label>
                        <SimpleSelect
                            aria-label="Filter by branch"
                            className="w-full sm:w-auto"
                            value={branchId ? String(branchId) : ""}
                            onChange={(value) =>
                                router.get(
                                    dashboard(),
                                    value ? { branch_id: value } : {},
                                    { preserveScroll: true },
                                )
                            }
                            placeholder="All branches"
                            options={[
                                { value: "", label: "All branches" },
                                ...branches.map((branch) => ({
                                    value: String(branch.id),
                                    label: branch.name,
                                })),
                            ]}
                        />
                    </div>
                    <Link href={posEntries.create()}>
                        <Button variant="outline" size="sm">
                            <BanknoteArrowDown className="size-4" />
                            Record POS entry
                        </Button>
                    </Link>
                    <Link href={journalEntries.create()}>
                        <Button size="sm">
                            <Plus className="size-4" />
                            New journal entry
                        </Button>
                    </Link>
                </>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-4">
                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <Badge variant="secondary" className="gap-1.5 py-1">
                        <CalendarClock className="size-3.5" />
                        {fiscalYear
                            ? `FY ${fiscalYear.year}`
                            : "No fiscal year"}
                        {period ? ` · ${period.name}` : ""}
                    </Badge>
                    {period && (
                        <Badge
                            variant="outline"
                            className={cn(
                                "gap-1.5 py-1",
                                period.allows_posting
                                    ? "text-emerald-700 dark:text-emerald-300"
                                    : "text-amber-700 dark:text-amber-300",
                            )}
                        >
                            {period.allows_posting ? (
                                <Unlock className="size-3.5" />
                            ) : (
                                <Lock className="size-3.5" />
                            )}
                            {period.status === "open"
                                ? "Posting open"
                                : `Posting ${period.status}`}
                        </Badge>
                    )}
                    {fiscalYear && (
                        <span className="text-muted-foreground">
                            {formatDate(fiscalYear.start_date)} –{" "}
                            {formatDate(fiscalYear.end_date)} ·{" "}
                            <Link
                                href={`/fiscal-years/${fiscalYear.id}/edit`}
                                className="text-primary hover:underline"
                            >
                                manage periods
                            </Link>
                        </span>
                    )}
                </div>

                {alerts.length > 0 && (
                    <div className="grid gap-2">
                        {alerts.map((alert) => (
                            <Alert
                                key={alert.message}
                                variant={
                                    alert.level === "error"
                                        ? "destructive"
                                        : "default"
                                }
                            >
                                {alert.level === "error" ? (
                                    <AlertCircle className="size-4" />
                                ) : (
                                    <TriangleAlert className="size-4" />
                                )}
                                <AlertTitle>
                                    {alert.level === "error"
                                        ? "Action needed"
                                        : "Heads up"}
                                </AlertTitle>
                                <AlertDescription className="flex flex-wrap items-center gap-2">
                                    <span>{alert.message}</span>
                                    {alert.href && (
                                        <Link href={alert.href}>
                                            <Button size="sm" variant="outline">
                                                {alert.action ?? "Open"}
                                            </Button>
                                        </Link>
                                    )}
                                </AlertDescription>
                            </Alert>
                        ))}
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {kpis.map((kpi) => {
                        const Icon = KPI_ICONS[kpi.key] ?? Wallet;

                        return (
                            <Card key={kpi.key}>
                                <CardContent className="p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="text-muted-foreground text-xs">
                                            {kpi.label}
                                        </p>
                                        <Icon className="text-muted-foreground size-4 shrink-0" />
                                    </div>
                                    <p className="mt-1 truncate text-xl font-semibold tabular-nums">
                                        {typeof kpi.cents === "number"
                                            ? formatCents(
                                                  kpi.cents,
                                                  company.currency,
                                              )
                                            : (kpi.count ?? 0)}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        {kpi.hint}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {/* <div className="grid gap-4 lg:grid-cols-2">
                    <AgingCard
                        title="Total Receivables"
                        aging={receivables}
                        currency={company.currency}
                        createHref="/sales/create"
                    />
                    <AgingCard
                        title="Total Payables"
                        aging={payables}
                        currency={company.currency}
                        createHref="/purchases/create"
                    />
                    <CashFlowChart
                        data={cashFlow}
                        currency={company.currency}
                    />
                    <IncomeExpenseChart
                        data={incomeExpense}
                        currency={company.currency}
                    />
                </div> */}

                <div className="grid gap-4 lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardContent className="p-0">
                            <div className="flex items-center justify-between gap-2 px-4 py-3">
                                <div>
                                    <p className="text-sm font-semibold">
                                        Recent journal entries
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        Manual journals and posted POS summaries
                                    </p>
                                </div>
                                <Link
                                    href={journalEntries.index()}
                                    className="text-primary text-xs hover:underline"
                                >
                                    View all
                                </Link>
                            </div>

                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-y text-left text-xs">
                                        <th className="px-4 py-2 font-medium">
                                            Date
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Reference
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Description
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentEntries.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="text-muted-foreground px-4 py-12 text-center text-sm"
                                            >
                                                Nothing posted yet.{" "}
                                                <Link
                                                    href={journalEntries.create()}
                                                    className="text-primary hover:underline"
                                                >
                                                    Create the first entry
                                                </Link>
                                                .
                                            </td>
                                        </tr>
                                    )}
                                    {recentEntries.map((entry) => (
                                        <tr
                                            key={entry.id}
                                            className="hover:bg-muted/30 border-b last:border-0"
                                        >
                                            <td className="text-muted-foreground px-4 py-2 whitespace-nowrap">
                                                {formatDate(
                                                    entry.transaction_date,
                                                )}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Link
                                                    href={`/journal-entries/${entry.id}`}
                                                    className="font-mono text-xs hover:underline"
                                                >
                                                    {entry.reference_no}
                                                </Link>
                                            </td>
                                            <td className="max-w-56 px-4 py-2">
                                                <span className="line-clamp-1">
                                                    {entry.description ?? "—"}
                                                </span>
                                                {entry.branch && (
                                                    <span className="text-muted-foreground text-xs">
                                                        {" "}
                                                        · {entry.branch}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-2">
                                                <span className="inline-flex items-center gap-1.5">
                                                    <StatusBadge
                                                        value={entry.status}
                                                    />
                                                    {entry.source_type ===
                                                        "pos" && (
                                                        <StatusBadge
                                                            value="pos"
                                                            className="h-4 px-1.5 text-[10px]"
                                                        />
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {formatCents(
                                                    entry.cents,
                                                    company.currency,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-sm font-semibold">
                                        Tenders today
                                    </p>
                                    <Link
                                        href="/pos-entries"
                                        className="text-primary text-xs hover:underline"
                                    >
                                        {posSummaries.posted} posted ·{" "}
                                        {posSummaries.drafts} draft
                                    </Link>
                                </div>

                                {tenderTotal === 0 ? (
                                    <p className="text-muted-foreground mt-4 mb-2 text-sm">
                                        No POS amounts recorded for{" "}
                                        {formatDate(businessDate)} yet.
                                    </p>
                                ) : (
                                    <div className="mt-3 space-y-2">
                                        {tenders.map((tender) => (
                                            <div
                                                key={tender.field}
                                                className="space-y-1"
                                            >
                                                <div className="flex items-baseline justify-between gap-2 text-sm">
                                                    <span className="text-muted-foreground truncate">
                                                        {tender.label}
                                                    </span>
                                                    <span className="tabular-nums">
                                                        {formatCents(
                                                            tender.cents,
                                                            company.currency,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                                    <div
                                                        className="bg-primary/70 h-full rounded-full"
                                                        style={{
                                                            width: `${
                                                                maxTender > 0
                                                                    ? Math.round(
                                                                          (tender.cents /
                                                                              maxTender) *
                                                                              100,
                                                                      )
                                                                    : 0
                                                            }%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                        <div className="flex items-baseline justify-between border-t pt-2 text-sm font-medium">
                                            <span>Total received</span>
                                            <span className="tabular-nums">
                                                {formatCents(
                                                    tenderTotal,
                                                    company.currency,
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="grid gap-2 p-4 sm:grid-cols-2">
                                {[
                                    {
                                        key: "new-journal-entry",
                                        label: "New journal entry",
                                        href: create(),
                                        icon: Plus,
                                    },
                                    {
                                        key: "pos-day-summary",
                                        label: "POS day summary",
                                        href: Poscreate(),
                                        icon: Eye,
                                    },
                                    {
                                        key: "trial-balance",
                                        label: "Trial balance",
                                        href: reports.trialBalance(),
                                        icon: Eye,
                                    },
                                    {
                                        key: "chart-of-accounts",
                                        label: "Chart of accounts",
                                        href: accounts.index(),
                                        icon: Eye,
                                    },
                                ].map((item) => {
                                    const Icon = item.icon;

                                    return (
                                        <Link key={item.key} href={item.href}>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full justify-start gap-2"
                                            >
                                                <Icon className="size-4" />
                                                {item.label}
                                            </Button>
                                        </Link>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
