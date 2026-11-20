import { Head } from "@inertiajs/react";
import { Upload } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { formatCents } from "@/lib/format";

type LedgerEntry = {
    id: number;
    date: string;
    description: string;
    payment_type: string | null;
    user: string | null;
    credit_cents: number | null;
    debit_cents: number | null;
    cumulative_balance_cents: number | null;
};

type Props = {
    entries: LedgerEntry[];
    currency: string;
};

export default function CityLedgerIndex({ entries, currency }: Props) {
    const [importOpen, setImportOpen] = useState(false);

    const money = (cents: number | null) =>
        cents !== null ? formatCents(cents, currency) : "—";

    return (
        <AuthenticatedLayout
            title="City Ledger"
            description="Accounts receivable transactions and running balance"
            actions={
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setImportOpen(true)}
                >
                    <Upload className="size-4" />
                    Import data
                </Button>
            }
        >
            <Head title="City Ledger" />

            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Import city ledger</DialogTitle>
                        <DialogDescription>
                            Import is not available yet. It will be enabled
                            once the accounts receivable computation is in
                            place.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setImportOpen(false)}
                        >
                            Close
                        </Button>
                        <Button type="button" disabled>
                            Import
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="space-y-4">
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Description
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Payment Type
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        User
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Credit
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Debit
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Cumulative Balance
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground px-4 py-12 text-center text-sm"
                                        >
                                            No ledger entries yet.
                                        </td>
                                    </tr>
                                )}

                                {entries.map((entry) => (
                                    <tr
                                        key={entry.id}
                                        className="hover:bg-muted/30 border-b last:border-0"
                                    >
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            {entry.date}
                                        </td>
                                        <td className="px-4 py-2">
                                            {entry.description}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2">
                                            {entry.payment_type ?? "—"}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2">
                                            {entry.user ?? "—"}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {money(entry.credit_cents)}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {money(entry.debit_cents)}
                                        </td>
                                        <td className="px-4 py-2 text-right font-medium tabular-nums">
                                            {money(
                                                entry.cumulative_balance_cents,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}