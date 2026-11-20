import { Head } from '@inertiajs/react';
import { ContactOption, JournalEntryForm } from '@/components/journal/journal-entry-form';
import type { AccountOption, BranchSummary, PeriodInfo } from '@/types/models';
// create.tsx
type Props = {
    accounts: AccountOption[];
    branches: BranchSummary[];
    contacts: ContactOption[]; // add
    period: PeriodInfo | null;
    defaults: {
        transaction_date: string;
        branch_id: number | null;
        contact_id: number | null; // add
        description: string;
        reference_no: string;
    };
    postingBlocked: boolean;
    currency: string;
};

export default function CreateJournalEntry({
    accounts,
    branches,
    contacts, // add
    period,
    defaults,
    postingBlocked,
    currency,
}: Props) {
    return (
        <>
            <Head title="New journal entry" />
            <JournalEntryForm
                action="/journal-entries"
                method="post"
                accounts={accounts}
                branches={branches}
                contacts={contacts} // add
                period={period}
                currency={currency}
                postingBlocked={postingBlocked}
                backHref="/journal-entries"
                heading={{
                    title: 'New journal entry',
                    description: 'Debits and credits must agree before the entry can be posted',
                }}
                initial={{
                    reference_no: defaults.reference_no,
                    transaction_date: defaults.transaction_date,
                    branch_id: defaults.branch_id ? String(defaults.branch_id) : '',
                    contact_id: defaults.contact_id ?? '', // add
                    description: defaults.description,
                }}
            />
        </>
    );
}
