// pages/expenses/edit.tsx
import { Head } from '@inertiajs/react';
import { ExpenseForm } from '@/components/expenses/expense-form';
import type { AccountGroup } from '@/components/journal/account-combobox';
import type { ExpenseLineInput } from '@/components/expenses/expense-lines-editor';
import { BranchOption, ContactOption } from '@/types/purchases/form';

type Props = {
    expense: {
        id: number;
        branch_id: string | null;
        contact_id: number | null;
        expense_date: string;
        reference_no: string | null;
        payee: string | null;
        description: string | null;
        notes: string | null;
        lines: ExpenseLineInput[];
    };
    branches: BranchOption[];
    contacts: ContactOption[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    vatRate: number;
    currency: string;
};

export default function EditExpense({
    expense,
    branches,
    contacts,
    accountOptions,
    withholdingRates,
    vatRate,
    currency,
}: Props) {
    return (
        <>
            <Head title="Edit expense" />
            <ExpenseForm
                action={`/expenses/${expense.id}`}
                method="put"
                branches={branches}
                contacts={contacts}
                accountOptions={accountOptions}
                withholdingRates={withholdingRates}
                vatRate={vatRate}
                currency={currency}
                backHref={`/expenses/${expense.id}`}
                heading={{
                    title: 'Edit expense',
                    description: 'Changes are saved to the draft until it is posted',
                }}
                initial={{
                    branch_id: expense.branch_id,
                    contact_id: expense.contact_id,
                    expense_date: expense.expense_date,
                    reference_no: expense.reference_no,
                    payee: expense.payee,
                    description: expense.description,
                    notes: expense.notes,
                    lines: expense.lines,
                }}
            />
        </>
    );
}