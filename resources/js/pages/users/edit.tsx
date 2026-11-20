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

type Props = {
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        roles: string[];
    };
    roles: string[];
};

type UserFormData = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles: string[];
    is_active: boolean;
};

export default function EditUser({ user, roles }: Props) {
    const form = useForm<UserFormData>({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        roles: user.roles,
        is_active: user.is_active,
    });

    const { data, setData, processing, errors } = form;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(`/users/${user.id}`);
    };

    const toggleRole = (role: string, checked: boolean) => {
        setData('roles', checked ? [...data.roles, role] : data.roles.filter((r) => r !== role));
    };

    return (
        <AuthenticatedLayout title={`Edit ${user.name}`} description="Update account details and roles">
            <div className="w-full space-y-4">
                <Link
                    href={`/users/${user.id}`}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to user
                </Link>

                <form onSubmit={submit} className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Account details</CardTitle>
                            <CardDescription>
                                Leave password blank to keep it unchanged
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Name" htmlFor="name" error={errors.name}>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(event) => setData('name', event.target.value)}
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

                            <Field label="New password" htmlFor="password" error={errors.password}>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    disabled={processing}
                                />
                            </Field>

                            <Field
                                label="Confirm new password"
                                htmlFor="password_confirmation"
                                error={errors.password_confirmation}
                            >
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(event) =>
                                        setData('password_confirmation', event.target.value)
                                    }
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

                    <Card>
                        <CardHeader>
                            <CardTitle>Roles</CardTitle>
                            <CardDescription>What this user is allowed to do</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {roles.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No roles exist yet.</p>
                            ) : (
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    {roles.map((role) => (
                                        <label
                                            key={role}
                                            className="text-muted-foreground flex items-center gap-2 text-sm capitalize"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={data.roles.includes(role)}
                                                onChange={(event) => toggleRole(role, event.target.checked)}
                                                className="accent-primary size-4"
                                                disabled={processing}
                                            />
                                            {role}
                                        </label>
                                    ))}
                                </div>
                            )}
                            {errors.roles && (
                                <p className="text-destructive mt-2 text-xs">{errors.roles}</p>
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
                            Update user
                        </Button>
                        <Link href={`/users/${user.id}`}>
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