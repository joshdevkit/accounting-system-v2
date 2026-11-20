import { Head } from "@inertiajs/react";
import { AccountGroup } from "../../components/journal/account-combobox";
import { PurchaseForm } from "@/components/purchases/purchase-form";
import { BranchOption, ContactOption } from "@/types/purchases/form";

type Props = {
    purchase: {
        id: number;
        branch_id: number | null;
        contact_id: number | null;
        purchase_date: string;
        due_date: string | null;
        debit_account_id?: number;
        reference_no: string | null;
        vendor_name: string | null;
        description: string | null;
        taxable_amount: number;
        tax_type: "vatable" | "non_vat" | "vat_exempt";
        withholding_tax_rate: number;
        notes: string | null;
        lines: {
            account_id: number | "";
            particulars: string;
            quantity: string;
            uom: string;
            unit_price: string;
            amount: string;
            tax_type: "vatable" | "non_vat" | "vat_exempt";
            withholding_tax_rate: string;
            description: string;
        }[];
    };
    branches: BranchOption[];
    contacts: ContactOption[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    vatRate: number;
    currency: string;
};

export default function EditPurchase({
    contacts,
    purchase,
    branches,
    accountOptions,
    withholdingRates,
    vatRate,
    currency,
}: Props) {
    return (
        <>
            <Head
                title={`Edit purchase · ${purchase.vendor_name ?? purchase.id}`}
            />
            <PurchaseForm
                contacts={contacts}
                action={`/purchases/${purchase.id}`}
                method="put"
                branches={branches}
                accountOptions={accountOptions}
                withholdingRates={withholdingRates}
                vatRate={vatRate}
                currency={currency}
                backHref={`/purchases/${purchase.id}`}
                heading={{
                    title: "Edit purchase",
                    description: "Only draft purchases can be changed",
                }}
                initial={{
                    branch_id: purchase.branch_id
                        ? String(purchase.branch_id)
                        : "",
                    contact_id: purchase.contact_id,
                    purchase_date: purchase.purchase_date,
                    due_date: purchase.due_date,
                    debit_account_id: purchase.debit_account_id,
                    reference_no: purchase.reference_no,
                    vendor_name: purchase.vendor_name,
                    description: purchase.description,
                    taxable_amount: purchase.taxable_amount,
                    tax_type: purchase.tax_type,
                    withholding_tax_rate: purchase.withholding_tax_rate,
                    notes: purchase.notes,
                    lines: purchase.lines,
                }}
            />
        </>
    );
}
