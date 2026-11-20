import { Head } from "@inertiajs/react";
import { ContactForm } from "@/components/contacts/contact-form";

export default function CreateContact() {
    return (
        <>
            <Head title="Add contact" />
            <ContactForm
                action="/contacts"
                method="post"
                backHref="/contacts"
                heading={{
                    title: "Add contact",
                    description: "A vendor or customer you deal with regularly",
                }}
                initial={{}}
            />
        </>
    );
}
