import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatCents, formatDate } from '@/lib/format';

type Entry = {
    id: number;
    expense_date: string;
    payee: string | null;
    reference_no: string | null;
    status: string;
    payment_status: 'paid' | 'unpaid';
    net_payable_cents: number;
    branch: string | null;
    account: string | null;
    journal_entry: { id: number; reference_no: string } | null;
};

type Props = {
    entries: Entry[];
    currency: string;
};

export default function ExpensesIndex({ entries, currency }: Props) {
    return (
        <AuthenticatedLayout
            title="Expenses"
            description="Day-to-day spending, posted to the ledger"
            actions={
                <Link href="/expenses/create">
                    <Button size="sm">
                        <Plus className="size-4" />
                        Record expense
                    </Button>
                </Link>
            }
        >
            <Head title="Expenses" />

            <Card>
                <CardContent className="p-0">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-b text-left text-xs">
                                <th className="px-4 py-2 font-medium">Date</th>
                                <th className="px-4 py-2 font-medium">Payee</th>
                                <th className="px-4 py-2 font-medium">Status</th>
                                <th className="px-4 py-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-4 py-12 text-center text-sm">
                                        No expenses recorded yet.
                                    </td>
                                </tr>
                            )}
                            {entries.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="hover:bg-muted/30 cursor-pointer border-b last:border-0"
                                    onClick={() => router.get(`/expenses/${entry.id}`)}
                                >
                                    <td className="text-muted-foreground px-4 py-2 whitespace-nowrap">
                                        {formatDate(entry.expense_date)}
                                    </td>
                                    <td className="px-4 py-2">{entry.payee ?? '—'}</td>
                                    <td className="px-4 py-2">
                                        <StatusBadge value={entry.status} />
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {formatCents(entry.net_payable_cents, currency)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}
