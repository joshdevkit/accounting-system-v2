import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Save } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export type ContactFormData = {
    type: 'vendor' | 'customer' | 'both';
    name: string;
    tax_identification_number: string;
    phone: string;
    email: string;
    address: string;
    default_payment_terms_days: string;
    notes: string;
    is_active: boolean;
};

export type ContactInitial = Partial<{
    type: 'vendor' | 'customer' | 'both';
    name: string;
    tax_identification_number: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    default_payment_terms_days: number;
    notes: string | null;
    is_active: boolean;
}>;

export function ContactForm({
    action,
    method,
    initial,
    backHref,
    heading,
}: {
    action: string;
    method: 'post' | 'put';
    initial: ContactInitial;
    backHref: string;
    heading: { title: string; description?: string };
}) {
    const form = useForm<ContactFormData>({
        type: initial.type ?? 'vendor',
        name: initial.name ?? '',
        tax_identification_number: initial.tax_identification_number ?? '',
        phone: initial.phone ?? '',
        email: initial.email ?? '',
        address: initial.address ?? '',
        default_payment_terms_days: String(initial.default_payment_terms_days ?? 30),
        notes: initial.notes ?? '',
        is_active: initial.is_active ?? true,
    });

    const { data, setData, processing, errors } = form;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form[method === 'put' ? 'put' : 'post'](action);
    };

    return (
        <AuthenticatedLayout title={heading.title} description={heading.description}>
            <div className="mx-auto max-w-full space-y-4">
                <Link
                    href={backHref}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to contacts
                </Link>

                <form onSubmit={submit} className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contact details</CardTitle>
                            <CardDescription>
                                Used across Purchases and Sales as the vendor or customer
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field
                                label="Name"
                                htmlFor="name"
                                error={errors.name}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>

                            <Field label="Type" htmlFor="type" error={errors.type}>
                                <Select
                                    id="type"
                                    value={data.type}
                                    onChange={(event) =>
                                        setData('type', event.target.value as ContactFormData['type'])
                                    }
                                    disabled={processing}
                                >
                                    <option value="vendor">Vendor</option>
                                    <option value="customer">Customer</option>
                                    <option value="both">Both</option>
                                </Select>
                            </Field>

                            <Field
                                label="Payment terms (days)"
                                htmlFor="default_payment_terms_days"
                                error={errors.default_payment_terms_days}
                                hint="Used to pre-fill the due date"
                            >
                                <Input
                                    id="default_payment_terms_days"
                                    type="number"
                                    min="0"
                                    max="365"
                                    value={data.default_payment_terms_days}
                                    onChange={(event) =>
                                        setData('default_payment_terms_days', event.target.value)
                                    }
                                    disabled={processing}
                                />
                            </Field>

                            <Field
                                label="TIN"
                                htmlFor="tax_identification_number"
                                error={errors.tax_identification_number}
                            >
                                <Input
                                    id="tax_identification_number"
                                    value={data.tax_identification_number}
                                    onChange={(event) =>
                                        setData('tax_identification_number', event.target.value)
                                    }
                                    disabled={processing}
                                />
                            </Field>

                            <Field label="Phone" htmlFor="phone" error={errors.phone}>
                                <Input
                                    id="phone"
                                    value={data.phone}
                                    onChange={(event) => setData('phone', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>

                            <Field label="Email" htmlFor="email" error={errors.email}>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) => setData('email', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>

                            <Field
                                label="Address"
                                htmlFor="address"
                                error={errors.address}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="address"
                                    rows={2}
                                    value={data.address}
                                    onChange={(event) => setData('address', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>

                            <Field
                                label="Notes"
                                htmlFor="notes"
                                error={errors.notes}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="notes"
                                    rows={2}
                                    value={data.notes}
                                    onChange={(event) => setData('notes', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>

                            <label className="text-muted-foreground flex items-center gap-2 text-sm sm:col-span-2">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(event) => setData('is_active', event.target.checked)}
                                    className="accent-primary size-4"
                                    disabled={processing}
                                />
                                Active
                            </label>
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            {method === 'put' ? 'Update contact' : 'Add contact'}
                        </Button>
                        <Link href={backHref}>
                            <Button type="button" variant="ghost">
                                Cancel
                            </Button>
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}