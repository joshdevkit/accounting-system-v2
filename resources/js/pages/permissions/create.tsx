import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Save } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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

type PermissionFormData = {
    name: string;
    label: string;
};

export default function CreatePermission() {
    const form = useForm<PermissionFormData>({ name: '', label: '' });
    const { data, setData, processing, errors } = form;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/permissions');
    };

    return (
        <AuthenticatedLayout title="Add permission" description="A capability that can be assigned to roles">
            <div className="w-full space-y-4">
                <Link
                    href="/permissions"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to permissions
                </Link>

                <Alert>
                    <AlertTitle>This doesn't restrict anything by itself</AlertTitle>
                    <AlertDescription>
                        Creating a permission only makes it assignable to roles. It only
                        actually restricts a page or action once a developer adds a check
                        for it in the code.
                    </AlertDescription>
                </Alert>

                <form onSubmit={submit} className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Permission details</CardTitle>
                            <CardDescription>
                                The name is what's checked in code — keep it lowercase and consistent
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field
                                label="Name"
                                htmlFor="name"
                                error={errors.name}
                                hint="e.g. manage inventory"
                            >
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>

                            <Field
                                label="Display label"
                                htmlFor="label"
                                error={errors.label}
                                hint="Shown on the Roles page — defaults to the name if left blank"
                            >
                                <Input
                                    id="label"
                                    value={data.label}
                                    placeholder="Manage inventory"
                                    onChange={(event) => setData('label', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            Add permission
                        </Button>
                        <Link href="/permissions">
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