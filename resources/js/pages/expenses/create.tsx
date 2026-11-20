// pages/expenses/create.tsx
import { Head } from '@inertiajs/react';
import { ExpenseForm } from '@/components/expenses/expense-form';
import type { AccountGroup } from '@/components/journal/account-combobox';
import { BranchOption, ContactOption } from '@/types/purchases/form';

type Props = {
    branches: BranchOption[];
    contacts: ContactOption[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    vatRate: number;
    currency: string;
    defaults: { expense_date: string };
};

export default function CreateExpense({
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
            <Head title="Record expense" />
            <ExpenseForm
                action="/expenses"
                method="post"
                branches={branches}
                contacts={contacts}
                accountOptions={accountOptions}
                withholdingRates={withholdingRates}
                vatRate={vatRate}
                currency={currency}
                backHref="/expenses"
                heading={{
                    title: 'Record expense',
                    description: 'A spend, accrued to Accounts Payable',
                }}
                initial={{ expense_date: defaults.expense_date }}
            />
        </>
    );
}