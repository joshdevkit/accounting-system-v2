import { Head } from "@inertiajs/react";
import { SaleForm } from "@/components/sales/sale-form";
import { AccountGroup } from "@/components/journal/account-combobox";

type Props = {
    branches: { id: number; name: string; code: string }[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    businessSources: { value: string; label: string }[];
    discounts: { value: string; label: string }[];
    vatRate: number;
    currency: string;
    defaults: {
        sale_date: string;
        business_source?: string | null;
        discount_type?: string | null;
    };
};

export default function CreateSale({
    branches,
    accountOptions,
    withholdingRates,
    businessSources,
    discounts,
    vatRate,
    currency,
    defaults,
}: Props) {
    return (
        <>
            <Head title="Record sale" />
            <SaleForm
                action="/sales"
                method="post"
                branches={branches}
                accountOptions={accountOptions}
                withholdingRates={withholdingRates}
                businessSources={businessSources}
                discounts={discounts}
                vatRate={vatRate}
                currency={currency}
                backHref="/sales"
                heading={{
                    title: "Record sale",
                    description:
                        "A customer invoice, accrued to Accounts Receivable",
                }}
                initial={{
                    sale_date: defaults.sale_date,
                    business_source: defaults.business_source ?? "",
                    discount_type: defaults.discount_type ?? "none",
                }}
            />
        </>
    );
}
