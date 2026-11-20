import { Head, Link, useForm } from '@inertiajs/react';
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

type Permission = { name: string; label: string };

type Props = {
    permissions: Permission[];
};

type RoleFormData = {
    name: string;
    permissions: string[];
};

export default function CreateRole({ permissions }: Props) {
    const form = useForm<RoleFormData>({ name: '', permissions: [] });
    const { data, setData, processing, errors } = form;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/roles');
    };

    const togglePermission = (name: string, checked: boolean) => {
        setData(
            'permissions',
            checked
                ? [...data.permissions, name]
                : data.permissions.filter((permission) => permission !== name),
        );
    };

    const toggleAll = (checked: boolean) => {
        setData('permissions', checked ? permissions.map((permission) => permission.name) : []);
    };

    const allChecked = permissions.length > 0 && data.permissions.length === permissions.length;

    return (
        <AuthenticatedLayout title="Add role" description="Define a role and what it can do">
            <div className="w-full space-y-4">
                <Link
                    href="/roles"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to roles
                </Link>

                <form onSubmit={submit} className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Role details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Field label="Name" htmlFor="name" error={errors.name} className="max-w-sm">
                                <Input
                                    id="name"
                                    value={data.name}
                                    placeholder="e.g. accountant"
                                    onChange={(event) => setData('name', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>Permissions</CardTitle>
                                <CardDescription>What this role is allowed to do</CardDescription>
                            </div>
                            <label className="text-muted-foreground flex items-center gap-2 text-xs">
                                <input
                                    type="checkbox"
                                    checked={allChecked}
                                    onChange={(event) => toggleAll(event.target.checked)}
                                    className="accent-primary size-4"
                                    disabled={processing}
                                />
                                Select all
                            </label>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {permissions.map((permission) => (
                                    <label
                                        key={permission.name}
                                        className="hover:bg-muted/30 flex items-center gap-2 rounded-md p-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.permissions.includes(permission.name)}
                                            onChange={(event) =>
                                                togglePermission(permission.name, event.target.checked)
                                            }
                                            className="accent-primary size-4"
                                            disabled={processing}
                                        />
                                        {permission.label}
                                    </label>
                                ))}
                            </div>
                            {errors.permissions && (
                                <p className="text-destructive mt-2 text-xs">{errors.permissions}</p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            Add role
                        </Button>
                        <Link href="/roles">
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