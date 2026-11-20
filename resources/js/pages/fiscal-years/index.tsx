import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarRange,
    Lock,
    LockOpen,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FiscalYearRow } from '@/types/models';

export default function FiscalYearsIndex({
    fiscalYears,
    stats,
}: {
    fiscalYears: (FiscalYearRow & { periods: FiscalYearRow['periods'] })[];
    stats: {
        years: number;
        open: number;
        periods: number;
        today: string;
    };
}) {
    const [expanded, setExpanded] = useState<number[]>(
        fiscalYears.slice(0, 1).map((year) => year.id),
    );

    const toggleStatus = (id: number, status: 'open' | 'closed') => {
        router.patch(`/fiscal-years/${id}/status`, {
            status: status === 'open' ? 'closed' : 'open',
        });
    };

    const togglePeriodStatus = (id: number, status: 'open' | 'closed') => {
        router.patch(`/accounting-periods/${id}/status`, {
            status: status === 'open' ? 'closed' : 'open',
        });
    };

    const remove = (fiscalYear: FiscalYearRow) => {
        if (
            !window.confirm(
                `Remove fiscal year ${fiscalYear.year} and its periods? Journal entries are not deleted.`,
            )
        ) {
            return;
        }

        router.delete(`/fiscal-years/${fiscalYear.id}`, {
            preserveScroll: true,
        });
    };

    const isOpenToday = (fiscalYear: FiscalYearRow) =>
        fiscalYear.start_date <= stats.today &&
        stats.today <= fiscalYear.end_date;

    return (
        <AuthenticatedLayout
            title="Fiscal years"
            description="The year and the monthly periods journal entries are filed into"
            actions={
                <Link href="/fiscal-years/create" className="inline-flex">
                    <Button size="sm">
                        <Plus className="size-4" />
                        New fiscal year
                    </Button>
                </Link>
            }
        >
            <Head title="Fiscal years" />

            {fiscalYears.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <div className="bg-muted flex size-10 items-center justify-center rounded-full">
                            <CalendarRange className="text-muted-foreground size-5" />
                        </div>
                        <div>
                            <p className="text-sm font-medium">
                                No fiscal year yet
                            </p>
                            <p className="text-muted-foreground mx-auto mt-1 max-w-sm text-xs">
                                Nothing can be posted to the ledger until a
                                fiscal year and its accounting periods exist.
                            </p>
                        </div>
                        <Link href="/fiscal-years/create">
                            <Button size="sm">
                                <Plus className="size-4" />
                                Create the first fiscal year
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {[
                            { label: 'Fiscal years', value: stats.years },
                            { label: 'Open years', value: stats.open },
                            { label: 'Periods', value: stats.periods },
                        ].map((item) => (
                            <div
                                key={item.label}
                                className="bg-card rounded-lg border px-4 py-3"
                            >
                                <p className="text-muted-foreground text-xs">
                                    {item.label}
                                </p>
                                <p className="mt-0.5 text-xl font-semibold">
                                    {item.value}
                                </p>
                            </div>
                        ))}
                    </div>

                    {fiscalYears.map((fiscalYear) => {
                        const open = expanded.includes(fiscalYear.id);

                        return (
                            <Card key={fiscalYear.id}>
                                <CardHeader className="flex-row items-center justify-between gap-3 space-y-0">
                                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                                        <CardTitle className="text-base">
                                            {fiscalYear.year}
                                        </CardTitle>
                                        <StatusBadge
                                            value={fiscalYear.status}
                                        />
                                        {isOpenToday(fiscalYear) && (
                                            <Badge variant="outline">
                                                covers today
                                            </Badge>
                                        )}
                                        <span className="text-muted-foreground text-xs">
                                            {formatDate(fiscalYear.start_date)}{' '}
                                            – {formatDate(fiscalYear.end_date)}
                                        </span>
                                    </div>

                                    <div className="flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setExpanded((current) =>
                                                    current.includes(
                                                        fiscalYear.id,
                                                    )
                                                        ? current.filter(
                                                              (id) =>
                                                                  id !==
                                                                  fiscalYear.id,
                                                          )
                                                        : [
                                                              ...current,
                                                              fiscalYear.id,
                                                          ],
                                                )
                                            }
                                        >
                                            {open
                                                ? 'Hide periods'
                                                : `Show ${fiscalYear.periods.length} periods`}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                toggleStatus(
                                                    fiscalYear.id,
                                                    fiscalYear.status,
                                                )
                                            }
                                        >
                                            {fiscalYear.status === 'open' ? (
                                                <>
                                                    <Lock className="size-4" />
                                                    Close year
                                                </>
                                            ) : (
                                                <>
                                                    <LockOpen className="size-4" />
                                                    Reopen
                                                </>
                                            )}
                                        </Button>
                                        <Link
                                            href={`/fiscal-years/${fiscalYear.id}/edit`}
                                            aria-label={`Edit ${fiscalYear.year}`}
                                            className="text-muted-foreground hover:bg-accent hover:text-foreground flex size-8 items-center justify-center rounded-md"
                                        >
                                            <Pencil className="size-3.5" />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => remove(fiscalYear)}
                                            aria-label={`Remove ${fiscalYear.year}`}
                                            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive flex size-8 items-center justify-center rounded-md"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </div>
                                </CardHeader>

                                {open && (
                                    <CardContent className="p-0">
                                        {fiscalYear.periods.length === 0 ? (
                                            <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm">
                                                <span>
                                                    This year has no accounting
                                                    periods yet.
                                                </span>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/fiscal-years/${fiscalYear.id}/periods`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Generate periods
                                                </Button>
                                            </div>
                                        ) : (
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="text-muted-foreground border-y text-left text-xs">
                                                        <th className="px-4 py-2 font-medium">
                                                            Period
                                                        </th>
                                                        <th className="px-4 py-2 font-medium">
                                                            Dates
                                                        </th>
                                                        <th className="px-4 py-2 font-medium">
                                                            Status
                                                        </th>
                                                        <th className="px-4 py-2 text-right font-medium">
                                                            Entries
                                                        </th>
                                                        <th className="px-4 py-2 text-right font-medium">
                                                            Posting
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {fiscalYear.periods.map(
                                                        (period) => (
                                                            <tr
                                                                key={period.id}
                                                                className="border-b last:border-0"
                                                            >
                                                                <td className="px-4 py-2.5">
                                                                    <span className="font-medium">
                                                                        {
                                                                            period.period_number
                                                                        }
                                                                    </span>
                                                                    <span className="text-muted-foreground ml-2">
                                                                        {
                                                                            period.name
                                                                        }
                                                                    </span>
                                                                </td>
                                                                <td className="text-muted-foreground px-4 py-2.5">
                                                                    {formatDate(
                                                                        period.start_date,
                                                                    )}{' '}
                                                                    –{' '}
                                                                    {formatDate(
                                                                        period.end_date,
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-2.5">
                                                                    <StatusBadge
                                                                        value={
                                                                            period.status
                                                                        }
                                                                    />
                                                                </td>
                                                                <td
                                                                    className={cn(
                                                                        'px-4 py-2.5 text-right tabular-nums',
                                                                        !period.entries &&
                                                                            'text-muted-foreground/60',
                                                                    )}
                                                                >
                                                                    {
                                                                        period.entries
                                                                    }
                                                                </td>
                                                                <td className="px-4 py-2.5 text-right">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            togglePeriodStatus(
                                                                                period.id,
                                                                                period.status,
                                                                            )
                                                                        }
                                                                        className="text-primary text-xs font-medium hover:underline"
                                                                    >
                                                                        {period.status ===
                                                                        'open'
                                                                            ? 'Close period'
                                                                            : 'Reopen period'}
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        )}
                                    </CardContent>
                                )}
                            </Card>
                        );
                    })}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
