import { Head } from '@inertiajs/react';
import { ContactOption, JournalEntryForm } from '@/components/journal/journal-entry-form';
import type {
    AccountOption,
    BranchSummary,
    JournalLineInput,
    PeriodInfo,
} from '@/types/models';

type Entry = {
    id: number;
    transaction_date: string;
    branch_id: number | null;
    contact_id: number | null;
    description: string;
    reference_no: string;
    lines: JournalLineInput[];
};

type Props = {
    entry: Entry;
    accounts: AccountOption[];
    branches: BranchSummary[];
    contacts: ContactOption[];
    period: PeriodInfo | null;
    postingBlocked: boolean;
    currency: string;
};

export default function EditJournalEntry({
    entry,
    accounts,
    branches,
    contacts,
    period,
    postingBlocked,
    currency,
}: Props) {
    return (
        <>
            <Head title={`Edit ${entry.reference_no}`} />

            <JournalEntryForm
                action={`/journal-entries/${entry.id}`}
                method="put"
                accounts={accounts}
                branches={branches}
                contacts={contacts}
                period={period}
                currency={currency}
                postingBlocked={postingBlocked}
                backHref={`/journal-entries/${entry.id}`}
                heading={{
                    title: 'Edit journal entry',
                    description:
                        'Debits and credits must agree before the entry can be posted',
                }}
                initial={{
                    reference_no: entry.reference_no,
                    transaction_date: entry.transaction_date,
                    branch_id:
                        entry.branch_id !== null
                            ? String(entry.branch_id)
                            : '',
                    contact_id: entry.contact_id ?? '',
                    description: entry.description ?? '',
                    lines: entry.lines,
                }}
            />
        </>
    );
}