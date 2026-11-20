import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';

type Props = {
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        roles: string[];
        created_at: string | null;
    };
};

export default function ShowUser({ user }: Props) {
    const remove = () => {
        if (!window.confirm(`Delete ${user.name}?`)) return;
        router.delete(`/users/${user.id}`, {
            onSuccess: () => router.get('/users'),
        });
    };

    return (
        <AuthenticatedLayout
            title={user.name}
            description={user.email}
            actions={
                <>
                    <Link href={`/users/${user.id}/edit`}>
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
            <Head title={user.name} />

            <div className="w-full space-y-4">
                <Link
                    href="/users"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to users
                </Link>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1.5 text-sm">
                        <Row label="Email" value={user.email} />
                        <Row label="Status" value={user.is_active ? 'Active' : 'Inactive'} />
                        <Row label="Created" value={user.created_at ? formatDateTime(user.created_at) : '—'} />
                        <div className="flex items-center justify-between gap-2 border-b pb-1.5 last:border-0 last:pb-0">
                            <span className="text-muted-foreground">Roles</span>
                            <div className="flex flex-wrap justify-end gap-1">
                                {user.roles.length === 0 ? (
                                    <span>—</span>
                                ) : (
                                    user.roles.map((role) => (
                                        <Badge key={role} variant="outline" className="capitalize">
                                            {role}
                                        </Badge>
                                    ))
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-2 border-b pb-1.5 last:border-0 last:pb-0">
            <span className="text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}