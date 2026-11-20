import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, Pencil, Trash2 } from "lucide-react";

import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCents, formatDate } from "@/lib/format";

type Props = {
    contact: {
        id: number;
        type: "vendor" | "customer" | "both";
        name: string;
        tax_identification_number: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
        default_payment_terms_days: number;
        notes: string | null;
        is_active: boolean;
        outstanding_payable_cents: number | null;
        outstanding_receivable_cents: number | null;
    };
    releasedPurchases: {
        id: number;
        purchase_date: string;
        reference_no: string | null;
        amount_cents: number;
        check_number: string | null;
        released_at: string | null;
    }[];
    recentSales: {
        id: number;
        sale_date: string;
        status: string;
        payment_status: string;
        net_receivable_cents: number;
    }[];
    currency: string;
};

export default function ShowContact({
    contact,
    releasedPurchases,
    recentSales,
    currency,
}: Props) {
    const remove = () => {
        router.delete(`/contacts/${contact.id}`, {
            onSuccess: () => router.get("/contacts"),
        });
    };

    return (
        <AuthenticatedLayout
            title={contact.name}
            description={
                contact.type === "both"
                    ? "Vendor & Customer"
                    : contact.type
            }
            actions={
                <>
                    <Link href={`/contacts/${contact.id}/edit`}>
                        <Button size="sm" variant="outline">
                            <Pencil className="size-4" />
                            Edit
                        </Button>
                    </Link>

                    <AlertDialog>
                        <AlertDialogTrigger
                            render={
                                <Button
                                    size="sm"
                                    variant="destructive"
                                />
                            }
                        >
                            <Trash2 className="size-4" />
                            <span className="sr-only">
                                Delete {contact.name}
                            </span>
                        </AlertDialogTrigger>

                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Delete contact?
                                </AlertDialogTitle>

                                <AlertDialogDescription>
                                    Are you sure you want to delete{" "}
                                    <span className="font-medium text-foreground">
                                        {contact.name}
                                    </span>
                                    ?
                                    <span className="mt-2 block">
                                        This action cannot be undone.
                                    </span>
                                </AlertDialogDescription>
                            </AlertDialogHeader>

                            <AlertDialogFooter>
                                <AlertDialogCancel>
                                    Cancel
                                </AlertDialogCancel>

                                <AlertDialogAction
                                    variant="destructive"
                                    onClick={remove}
                                >
                                    Delete contact
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </>
            }
        >
            <Head title={contact.name} />

            <div className="space-y-4">
                <Link
                    href="/contacts"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to contacts
                </Link>

                <div className="grid gap-4 sm:grid-cols-2">
                    {contact.outstanding_payable_cents !== null && (
                        <Card>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-xs">
                                    Outstanding payable
                                </p>

                                <p className="text-xl font-semibold tabular-nums">
                                    {formatCents(
                                        contact.outstanding_payable_cents,
                                        currency,
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {contact.outstanding_receivable_cents !== null && (
                        <Card>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-xs">
                                    Outstanding receivable
                                </p>

                                <p className="text-xl font-semibold tabular-nums">
                                    {formatCents(
                                        contact.outstanding_receivable_cents,
                                        currency,
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-1.5 text-sm">
                        <Row
                            label="TIN"
                            value={
                                contact.tax_identification_number ?? "—"
                            }
                        />

                        <Row
                            label="Phone"
                            value={contact.phone ?? "—"}
                        />

                        <Row
                            label="Email"
                            value={contact.email ?? "—"}
                        />

                        <Row
                            label="Address"
                            value={contact.address ?? "—"}
                        />

                        <Row
                            label="Payment terms"
                            value={`${contact.default_payment_terms_days} days`}
                        />

                        <Row
                            label="Status"
                            value={
                                contact.is_active
                                    ? "Active"
                                    : "Inactive"
                            }
                        />

                        {contact.notes && (
                            <Row
                                label="Notes"
                                value={contact.notes}
                            />
                        )}
                    </CardContent>
                </Card>

                {contact.outstanding_payable_cents !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Purchases</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b text-left text-xs">
                                        <th className="px-4 py-2 font-medium">Purchase Date</th>
                                        <th className="px-4 py-2 font-medium">SI No. (Reference)</th>
                                        <th className="px-4 py-2 text-right font-medium">Amount</th>
                                        <th className="px-4 py-2 font-medium">Cheque No.</th>
                                        <th className="px-4 py-2 text-right font-medium">Date Released</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {releasedPurchases.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-muted-foreground px-4 py-8 text-center">
                                                No released purchases found.
                                            </td>
                                        </tr>
                                    ) : releasedPurchases.map((purchase) => (
                                        <tr
                                            key={purchase.id}
                                            className="hover:bg-muted/30 cursor-pointer border-b last:border-0"
                                            onClick={() => router.get(`/purchases/${purchase.id}`)}
                                        >
                                            <td className="text-muted-foreground px-4 py-2 whitespace-nowrap">
                                                {formatDate(purchase.purchase_date)}
                                            </td>
                                            <td className="px-4 py-2">{purchase.reference_no ?? "—"}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {formatCents(purchase.amount_cents, currency)}
                                            </td>
                                            <td className="px-4 py-2">{purchase.check_number ?? "—"}</td>
                                            <td className="text-muted-foreground px-4 py-2 text-right whitespace-nowrap">
                                                {purchase.released_at ? formatDate(purchase.released_at) : "—"}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                {recentSales.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Recent sales
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-1.5 text-sm">
                            {recentSales.map((sale) => (
                                <Link
                                    key={sale.id}
                                    href={`/sales/${sale.id}`}
                                    className="hover:bg-muted/30 flex items-center justify-between gap-2 rounded-md border-b px-2 py-1.5 last:border-0"
                                >
                                    <span className="text-muted-foreground">
                                        {formatDate(sale.sale_date)}
                                    </span>

                                    <StatusBadge
                                        value={
                                            sale.payment_status
                                        }
                                    />

                                    <span className="tabular-nums">
                                        {formatCents(
                                            sale.net_receivable_cents,
                                            currency,
                                        )}
                                    </span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function Row({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center justify-between gap-2 border-b pb-1.5 last:border-0 last:pb-0">
            <span className="text-muted-foreground">
                {label}
            </span>

            <span>{value}</span>
        </div>
    );
}
