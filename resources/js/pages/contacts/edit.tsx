import { Head } from '@inertiajs/react';
import { ContactForm } from '@/components/contacts/contact-form';

type Props = {
    contact: {
        id: number;
        type: 'vendor' | 'customer' | 'both';
        name: string;
        tax_identification_number: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
        default_payment_terms_days: number;
        notes: string | null;
        is_active: boolean;
    };
};

export default function EditContact({ contact }: Props) {
    return (
        <>
            <Head title={`Edit ${contact.name}`} />
            <ContactForm
                action={`/contacts/${contact.id}`}
                method="put"
                backHref={`/contacts/${contact.id}`}
                heading={{ title: 'Edit contact' }}
                initial={contact}
            />
        </>
    );
}