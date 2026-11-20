import { Head } from '@inertiajs/react';
import { SaleForm } from '@/components/sales/sale-form';
import { AccountGroup } from '@/components/journal/account-combobox';

type Props = {
    sale: {
        id: number;
        branch_id: number | null;
        sale_date: string;
        credit_account_id: number | null;
        reference_no: string | null;
        customer_name: string | null;
        taxable_amount: number;
        tax_type: 'vatable' | 'non_vat' | 'vat_exempt';
        business_source: string | null;
        discount_type: string | null;
        withholding_tax_rate: number;
        notes: string | null;
    };
    branches: { id: number; name: string; code: string }[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    businessSources: { value: string; label: string }[];
    discounts: { value: string; label: string }[];
    vatRate: number;
    currency: string;
};

export default function EditSale({
    sale,
    branches,
    accountOptions,
    withholdingRates,
    businessSources,
    discounts,
    vatRate,
    currency,
}: Props) {
    return (
        <>
            <Head title={`Edit sale · ${sale.customer_name ?? sale.id}`} />
            <SaleForm
                action={`/sales/${sale.id}`}
                method="put"
                branches={branches}
                accountOptions={accountOptions}
                withholdingRates={withholdingRates}
                businessSources={businessSources}
                discounts={discounts}
                vatRate={vatRate}
                currency={currency}
                backHref={`/sales/${sale.id}`}
                heading={{
                    title: 'Edit sale',
                    description: 'Only draft sales can be changed',
                }}
                initial={{
                    branch_id: sale.branch_id ? String(sale.branch_id) : '',
                    sale_date: sale.sale_date,
                    credit_account_id: sale.credit_account_id,
                    reference_no: sale.reference_no,
                    customer_name: sale.customer_name,
                    taxable_amount: sale.taxable_amount,
                    tax_type: sale.tax_type,
                    business_source: sale.business_source,
                    discount_type: sale.discount_type,
                    withholding_tax_rate: sale.withholding_tax_rate,
                    notes: sale.notes,
                }}
            />
        </>
    );
}