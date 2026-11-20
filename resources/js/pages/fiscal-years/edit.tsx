import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Lock,
    LockOpen,
    LoaderCircle,
    RefreshCw,
} from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatDate } from '@/lib/format';
import type { FiscalYearRow } from '@/types/models';

type Props = {
    fiscalYear: FiscalYearRow;
    periodsPerYear: number;
};

export default function EditFiscalYear({ fiscalYear, periodsPerYear }: Props) {
    const hasPostings = fiscalYear.entries > 0;

    const { data, setData, put, processing, errors } = useForm({
        year: String(fiscalYear.year),
        start_date: fiscalYear.start_date,
        end_date: fiscalYear.end_date,
        status: fiscalYear.status,
    });

    const patchStatus = (periodId: number, status: 'open' | 'closed') => {
        router.patch(
            `/accounting-periods/${periodId}/status`,
            { status: status === 'open' ? 'closed' : 'open' },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            title={`Fiscal year ${fiscalYear.year}`}
            description="Dates, periods and what is still open for posting"
            actions={
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.patch(
                            `/fiscal-years/${fiscalYear.id}/status`,
                            {
                                status:
                                    fiscalYear.status === 'open'
                                        ? 'closed'
                                        : 'open',
                            },
                            { preserveScroll: true },
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
                            Reopen year
                        </>
                    )}
                </Button>
            }
        >
            <Head title={`Fiscal year ${fiscalYear.year}`} />

            <div className="max-w-full space-y-4">
                <Link
                    href="/fiscal-years"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to fiscal years
                </Link>

                {hasPostings && (
                    <Alert>
                        <AlertTitle>
                            {fiscalYear.entries} journal entries are filed in
                            this year
                        </AlertTitle>
                        <AlertDescription>
                            The dates are locked while entries exist, and
                            periods that already carry entries cannot be
                            rebuilt.
                        </AlertDescription>
                    </Alert>
                )}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        put(`/fiscal-years/${fiscalYear.id}`);
                    }}
                    className="space-y-4"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Dates</CardTitle>
                            <CardDescription>
                                Periods are rebuilt from these dates, skipping
                                any period that has entries
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <Field
                                label="Year"
                                htmlFor="year"
                                error={errors.year}
                            >
                                <Input
                                    id="year"
                                    type="number"
                                    value={data.year}
                                    onChange={(event) =>
                                        setData('year', event.target.value)
                                    }
                                    aria-invalid={!!errors.year}
                                    disabled={processing || hasPostings}
                                />
                            </Field>
                            <Field
                                label="Start date"
                                htmlFor="start_date"
                                error={errors.start_date}
                            >
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(event) =>
                                        setData(
                                            'start_date',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!errors.start_date}
                                    disabled={processing || hasPostings}
                                />
                            </Field>
                            <Field
                                label="End date"
                                htmlFor="end_date"
                                error={errors.end_date}
                            >
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={data.end_date}
                                    onChange={(event) =>
                                        setData('end_date', event.target.value)
                                    }
                                    aria-invalid={!!errors.end_date}
                                    disabled={processing || hasPostings}
                                />
                            </Field>
                            <Field
                                label="Status"
                                htmlFor="status"
                                error={errors.status}
                            >
                                <Select
                                    id="status"
                                    value={data.status}
                                    onChange={(event) =>
                                        setData(
                                            'status',
                                            event.target
                                                .value as typeof data.status,
                                        )
                                    }
                                    disabled={processing}
                                >
                                    <option value="open">Open</option>
                                    <option value="closed">Closed</option>
                                </Select>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>Accounting periods</CardTitle>
                                <CardDescription>
                                    Closing a period blocks new postings into
                                    it, it never removes anything
                                </CardDescription>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/fiscal-years/${fiscalYear.id}/periods`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw className="size-4" />
                                Generate missing
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            {fiscalYear.periods.length === 0 ? (
                                <p className="text-muted-foreground px-4 py-8 text-center text-sm">
                                    No periods yet — generate {periodsPerYear}{' '}
                                    of them with the button above.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-muted-foreground border-y text-left text-xs">
                                            <th className="px-4 py-2 font-medium">
                                                #
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Period
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Dates
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Entries
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {fiscalYear.periods.map((period) => (
                                            <tr
                                                key={period.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="text-muted-foreground px-4 py-2.5 tabular-nums">
                                                    {period.period_number}
                                                </td>
                                                <td className="px-4 py-2.5 font-medium">
                                                    {period.name}
                                                    {!!period.drafts && (
                                                        <span className="ml-2 text-xs font-normal text-amber-700 dark:text-amber-400">
                                                            {period.drafts}{' '}
                                                            draft
                                                            {period.drafts === 1
                                                                ? ''
                                                                : 's'}
                                                        </span>
                                                    )}
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
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {period.entries}
                                                </td>
                                                <td className="px-4 py-2.5 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            patchStatus(
                                                                period.id,
                                                                period.status,
                                                            )
                                                        }
                                                        className="inline-flex items-center gap-2"
                                                    >
                                                        <StatusBadge
                                                            value={
                                                                period.status
                                                            }
                                                        />
                                                        <span className="text-muted-foreground text-xs underline-offset-2 hover:underline">
                                                            {period.status ===
                                                            'open'
                                                                ? 'Close'
                                                                : 'Reopen'}
                                                        </span>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && (
                                <LoaderCircle className="size-4 animate-spin" />
                            )}
                            Save changes
                        </Button>
                        <Link href="/fiscal-years">
                            <Button type="button" variant="ghost">
                                Cancel
                            </Button>
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
