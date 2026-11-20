import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Company } from '@/types/models';

export default function CompanySettings({ company }: { company: Company | null }) {
    return (
        <AuthenticatedLayout
            title="Company settings"
            description="Details used across invoices, reports, and journal entries"
        >
            <Head title="Company settings" />

            <Form
                action="/settings/company"
                method="put"
                className="max-w-full space-y-6"
            >
                {({ errors, processing, recentlySuccessful }) => (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Company details</CardTitle>
                                <CardDescription>
                                    Your company's legal and tax information
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="code">
                                            Company code
                                        </Label>
                                        <Input
                                            id="code"
                                            name="code"
                                            placeholder="MHTL"
                                            defaultValue={company?.code}
                                            aria-invalid={!!errors.code}
                                            disabled={processing}
                                        />
                                        {errors.code && (
                                            <p className="text-sm text-destructive">
                                                {errors.code}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="base_currency_code">
                                            Base currency
                                        </Label>
                                        <Input
                                            id="base_currency_code"
                                            name="base_currency_code"
                                            placeholder="PHP"
                                            maxLength={3}
                                            defaultValue={
                                                company?.base_currency_code ??
                                                'PHP'
                                            }
                                            aria-invalid={
                                                !!errors.base_currency_code
                                            }
                                            disabled={processing}
                                        />
                                        {errors.base_currency_code && (
                                            <p className="text-sm text-destructive">
                                                {errors.base_currency_code}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="name">Company name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        placeholder="The Maximilian Hotel"
                                        defaultValue={company?.name}
                                        aria-invalid={!!errors.name}
                                        disabled={processing}
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-destructive">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="tax_identification_number">
                                        Tax identification number
                                    </Label>
                                    <Input
                                        id="tax_identification_number"
                                        name="tax_identification_number"
                                        defaultValue={
                                            company?.tax_identification_number ??
                                            ''
                                        }
                                        aria-invalid={
                                            !!errors.tax_identification_number
                                        }
                                        disabled={processing}
                                    />
                                    {errors.tax_identification_number && (
                                        <p className="text-sm text-destructive">
                                            {errors.tax_identification_number}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Contact information</CardTitle>
                                <CardDescription>
                                    Shown on reports and printed documents
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="address">Address</Label>
                                    <Textarea
                                        id="address"
                                        name="address"
                                        rows={3}
                                        defaultValue={company?.address ?? ''}
                                        aria-invalid={!!errors.address}
                                        disabled={processing}
                                    />
                                    {errors.address && (
                                        <p className="text-sm text-destructive">
                                            {errors.address}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            defaultValue={company?.phone ?? ''}
                                            aria-invalid={!!errors.phone}
                                            disabled={processing}
                                        />
                                        {errors.phone && (
                                            <p className="text-sm text-destructive">
                                                {errors.phone}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            defaultValue={company?.email ?? ''}
                                            aria-invalid={!!errors.email}
                                            disabled={processing}
                                        />
                                        {errors.email && (
                                            <p className="text-sm text-destructive">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={processing}>
                                {processing && (
                                    <LoaderCircle className="size-4 animate-spin" />
                                )}
                                {processing ? 'Saving...' : 'Save changes'}
                            </Button>
                            {recentlySuccessful && (
                                <p className="text-sm text-muted-foreground">
                                    Saved.
                                </p>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </AuthenticatedLayout>
    );
}