import { Head, Link, router, useForm } from "@inertiajs/react";
import { Plus, Upload } from "lucide-react";
import { useState, type FormEvent } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Badge } from "@/components/ui/badge";
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
import { Input } from "@/components/ui/input";

type Contact = {
    id: number;
    name: string;
    contact_person: string | null;
    type: "vendor" | "customer" | "both";
    phone: string | null;
    email: string | null;
    is_active: boolean;
    total_debit_cents: number | null;
    total_credit_cents: number | null;
    balance_cents: number | null;
    outstanding_payable_cents: number | null;
    outstanding_receivable_cents: number | null;
};

type Props = {
    contacts: Contact[];
    filters: { type: string; q: string };
    currency: string;
};

export default function ContactsIndex({ contacts, filters, currency }: Props) {
    const isCustomerView = filters.type === "customer";

    const money = (cents: number | null) =>
        cents !== null ? formatCents(cents, currency) : "—";

    const [importOpen, setImportOpen] = useState(false);
    const importForm = useForm<{ file: File | null }>({ file: null });

    const handleImportOpenChange = (open: boolean) => {
        setImportOpen(open);

        if (!open) {
            importForm.reset();
            importForm.clearErrors();
        }
    };

    const submitImport = (event: FormEvent) => {
        event.preventDefault();

        importForm.post("/contacts/import", {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => handleImportOpenChange(false),
        });
    };

    return (
        <AuthenticatedLayout
            title="Contacts"
            description={isCustomerView ? 'Guest Masterdata' : 'Vendor Masterdata'}
            actions={
                <div className="flex items-center gap-2">
                    {isCustomerView && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setImportOpen(true)}
                        >
                            <Upload className="size-4" />
                            Import data
                        </Button>
                    )}
                    {!isCustomerView && (
                        <Link href="/contacts/create">
                        <Button size="sm">
                            <Plus className="size-4" />
                            Add contact
                        </Button>
                    </Link>
                    )}
                </div>
            }
        >
            <Head title="Contacts" />

            {isCustomerView && (
                <Dialog
                    open={importOpen}
                    onOpenChange={handleImportOpenChange}
                >
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={submitImport} className="space-y-4">
                            <DialogHeader>
                                <DialogTitle>Import guests</DialogTitle>
                                <DialogDescription>
                                    Upload a CSV file with these columns:
                                    Company, Contact Person, Email, Phone No.,
                                    Status.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-1">
                                <label
                                    htmlFor="import-file"
                                    className="text-muted-foreground text-xs"
                                >
                                    CSV file
                                </label>
                                <Input
                                    id="import-file"
                                    type="file"
                                    accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    className="block w-full text-sm"
                                    onChange={(event) =>
                                        importForm.setData(
                                            "file",
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                {importForm.errors.file && (
                                    <p className="text-destructive text-sm">
                                        {importForm.errors.file}
                                    </p>
                                )}
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        handleImportOpenChange(false)
                                    }
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        !importForm.data.file ||
                                        importForm.processing
                                    }
                                >
                                    {importForm.processing
                                        ? "Importing…"
                                        : "Import"}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}

            <div className="space-y-4">
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    {isCustomerView ? (
                                        <>
                                            <th className="px-4 py-2 font-medium">
                                                Company
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Contact Person
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Email
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Phone No.
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Status
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Total Debit
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Total Credit
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Balance
                                            </th>
                                        </>
                                    ) : (
                                        <>
                                            <th className="px-4 py-2 font-medium">
                                                Name
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Type
                                            </th>
                                        </>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {contacts.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={isCustomerView ? 8 : 2}
                                            className="text-muted-foreground px-4 py-12 text-center text-sm"
                                        >
                                            No contacts yet.
                                        </td>
                                    </tr>
                                )}

                                {contacts.map((contact) => (
                                    <tr
                                        key={contact.id}
                                        className="hover:bg-muted/30 cursor-pointer border-b last:border-0"
                                        onClick={() =>
                                            router.get(
                                                `/contacts/${contact.id}`,
                                            )
                                        }
                                    >
                                        <td className="px-4 py-2">
                                            {contact.name}
                                        </td>

                                        {isCustomerView ? (
                                            <>
                                                <td className="px-4 py-2">
                                                    {contact.contact_person ??
                                                        "—"}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-2">
                                                    {contact.email ?? "—"}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-2">
                                                    {contact.phone ?? "—"}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Badge
                                                        variant={
                                                            contact.is_active
                                                                ? "default"
                                                                : "outline"
                                                        }
                                                    >
                                                        {contact.is_active
                                                            ? "Active"
                                                            : "Inactive"}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {money(
                                                        contact.total_debit_cents,
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {money(
                                                        contact.total_credit_cents,
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-right font-medium tabular-nums">
                                                    {money(
                                                        contact.balance_cents,
                                                    )}
                                                </td>
                                            </>
                                        ) : (
                                            <td className="text-muted-foreground px-4 py-2 capitalize">
                                                {contact.type}
                                            </td>
                                        )}
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