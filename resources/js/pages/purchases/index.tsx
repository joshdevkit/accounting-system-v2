import { Head, Link, router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCents, formatDate } from "@/lib/format";

type Entry = {
    id: number;
    purchase_date: string;
    vendor: {
        id: number;
        name: string;
        tax_identification_number: string | null;
        address: string | null;
    } | null;
    vendor_name: string | null;
    reference_no: string | null;
    status: string;
    payment_status: "unpaid" | "paid" | "for_check";
    net_payable_cents: number;
    branch: string | null;
    account: string | null;
    journal_entry: { id: number; reference_no: string } | null;
};

type Props = {
    entries: Entry[];
    filters: { include_paid: boolean; voided: boolean };
    currency: string;
};

export default function PurchasesIndex({ entries, filters, currency }: Props) {
    return (
        <AuthenticatedLayout
            title="Purchases"
            description="Vendor bills, accrued to Accounts Payable"
            actions={
                <Link href="/purchases/create">
                    <Button size="sm">
                        <Plus className="size-4" />
                        Record purchase
                    </Button>
                </Link>
            }
        >
            <Head title="Purchases" />

            <Card>
                <CardContent className="p-0">
                    <div className="flex items-center justify-end border-b px-4 py-2">
                        <Checkbox
                            label="Show paid & Voided"
                            checked={filters.include_paid}
                            onCheckedChange={(checked) =>
                                router.get(
                                    "/purchases",
                                    checked ? { include_paid: 1 } : {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                    },
                                )
                            }
                        />
                    </div>

                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-b text-left text-xs">
                                <th className="px-4 py-2 font-medium">Date</th>
                                <th className="px-4 py-2 font-medium">
                                    Vendor
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Account
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Payment
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Net payable
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-12 text-center text-sm"
                                    >
                                        {filters.include_paid
                                            ? "No purchases recorded yet."
                                            : "No unpaid purchases. Tick “Show paid & Voided” to see settled purchases."}
                                    </td>
                                </tr>
                            )}
                            {entries.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="hover:bg-muted/30 cursor-pointer border-b last:border-0"
                                    onClick={() =>
                                        router.get(`/purchases/${entry.id}`)
                                    }
                                >
                                    <td className="text-muted-foreground px-4 py-2 whitespace-nowrap">
                                        {formatDate(entry.purchase_date)}
                                    </td>
                                    <td className="px-4 py-2">
                                        {entry.vendor?.name ??
                                            entry.vendor_name ??
                                            "—"}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-2">
                                        {entry.account ?? "—"}
                                    </td>
                                    <td className="px-4 py-2">
                                        <StatusBadge value={entry.status} />
                                    </td>
                                    <td className="px-4 py-2">
                                        <StatusBadge
                                            value={entry.payment_status}
                                            label={
                                                entry.payment_status ===
                                                "for_check"
                                                    ? "For Cheque"
                                                    : undefined
                                            }
                                        />
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {formatCents(
                                            entry.net_payable_cents,
                                            currency,
                                        )}
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
