import { Head } from '@inertiajs/react';
import type { AccountGroup } from '@/components/journal/account-combobox';
import { PurchaseForm } from '@/components/purchases/purchase-form';
import { BranchOption, ContactOption } from '@/types/purchases/form';

type Props = {
    branches: BranchOption[];
    contacts: ContactOption[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    vatRate: number;
    currency: string;
    defaults: { purchase_date: string; due_date: string | null };
};

export default function CreatePurchase({
    branches,
    contacts,
    accountOptions,
    withholdingRates,
    vatRate,
    currency,
    defaults,
}: Props) {
    return (
        <>
            <Head title="Record purchase" />
            <PurchaseForm
                action="/purchases"
                method="post"
                branches={branches}
                contacts={contacts}
                accountOptions={accountOptions}
                withholdingRates={withholdingRates}
                vatRate={vatRate}
                currency={currency}
                backHref="/purchases"
                heading={{
                    title: 'Record purchase',
                    description: 'A vendor bill, accrued to Accounts Payable',
                }}
                initial={{
                    purchase_date: defaults.purchase_date,
                    due_date: defaults.due_date ?? '',
                }}
            />
        </>
    );
}