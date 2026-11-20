import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Permission = { name: string; label: string };

type Props = {
    role: {
        id: number;
        name: string;
        permissions: string[];
        user_count: number;
    };
    permissions: Permission[];
};

export default function ShowRole({ role, permissions }: Props) {
    const labelFor = (name: string) =>
        permissions.find((permission) => permission.name === name)?.label ?? name;

    const remove = () => {
        if (!window.confirm(`Delete role "${role.name}"?`)) return;
        router.delete(`/roles/${role.id}`, {
            onSuccess: () => router.get('/roles'),
        });
    };

    return (
        <AuthenticatedLayout
            title={role.name}
            description={`${role.user_count} user${role.user_count === 1 ? '' : 's'} assigned`}
            actions={
                <>
                    <Link href={`/roles/${role.id}/edit`}>
                        <Button size="sm" variant="outline">
                            <Pencil className="size-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button size="sm" variant="destructive" onClick={remove}>
                        <Trash2 className="size-4" />
                    </Button>
                </>
            }
        >
            <Head title={role.name} />

            <div className="w-full space-y-4">
                <Link
                    href="/roles"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to roles
                </Link>

                <Card>
                    <CardHeader>
                        <CardTitle>Permissions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {role.permissions.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                This role has no permissions yet.
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-1.5">
                                {role.permissions.map((permission) => (
                                    <Badge key={permission} variant="outline">
                                        {labelFor(permission)}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}