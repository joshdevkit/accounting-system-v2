import { Head, Link, router } from '@inertiajs/react';
import { ImportIcon, Plus } from 'lucide-react';
import { useState } from 'react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatCents, formatDate } from '@/lib/format';
import SalesImportPreviewDialog from '@/components/sales/SalesImportPreviewDialog';

type Entry = {
    id: number;
    sale_date: string;
    customer_name: string | null;
    reference_no: string | null;
    status: string;
    payment_status: 'unpaid' | 'paid';
    net_receivable_cents: number;
    branch: string | null;
    account: string | null;
    journal_entry: { id: number; reference_no: string } | null;
};

type Branch = { id: number; name: string; code: string };

type PreviewData = {
    fileName: string;
    headers: (string | null)[];
    rows: (string | null)[][];
    totalRows: number;
    truncated: boolean;
};

type Props = {
    entries: Entry[];
    currency: string;
    branches: Branch[];
    preview?: PreviewData;
};

export default function SalesIndex({ entries, currency, branches, preview }: Props) {
    const [importOpen, setImportOpen] = useState(!!preview);

    return (
        <AuthenticatedLayout
            title="Sales"
            description="Customer invoices, accrued to Accounts Receivable"
            actions={
                <>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setImportOpen(true)}
                    >
                        <ImportIcon className="size-4" />
                        Import Sales Data
                    </Button>
                    <Link href="/sales/create">
                        <Button size="sm">
                            <Plus className="size-4" />
                            Record sale
                        </Button>
                    </Link>
                </>
            }
        >
            <Head title="Sales" />

            <Card>
                <CardContent className="p-0">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-b text-left text-xs">
                                <th className="px-4 py-2 font-medium">Date</th>
                                <th className="px-4 py-2 font-medium">Customer</th>
                                <th className="px-4 py-2 font-medium">Account</th>
                                <th className="px-4 py-2 font-medium">Status</th>
                                <th className="px-4 py-2 font-medium">Payment</th>
                                <th className="px-4 py-2 text-right font-medium">Net receivable</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-12 text-center text-sm">
                                        No sales recorded yet.
                                    </td>
                                </tr>
                            )}
                            {entries.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="hover:bg-muted/30 cursor-pointer border-b last:border-0"
                                    onClick={() => router.get(`/sales/${entry.id}`)}
                                >
                                    <td className="text-muted-foreground px-4 py-2 whitespace-nowrap">
                                        {formatDate(entry.sale_date)}
                                    </td>
                                    <td className="px-4 py-2">{entry.customer_name ?? '—'}</td>
                                    <td className="text-muted-foreground px-4 py-2">
                                        {entry.account ?? '—'}
                                    </td>
                                    <td className="px-4 py-2">
                                        <StatusBadge value={entry.status} />
                                    </td>
                                    <td className="px-4 py-2">
                                        <StatusBadge value={entry.payment_status} />
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {formatCents(entry.net_receivable_cents, currency)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <SalesImportPreviewDialog
                open={importOpen}
                onOpenChange={setImportOpen}
                branches={branches}
                preview={preview}
            />
        </AuthenticatedLayout>
    );
}