import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type User = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles: string[];
};

type Props = {
    users: User[];
    filters: { q: string };
};

export default function UsersIndex({ users, filters }: Props) {
    const apply = (next: Record<string, unknown>) => {
        router.get('/users', { ...filters, ...next }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            title="Users"
            description="Everyone with access to this system"
            actions={
                <Link href="/users/create">
                    <Button size="sm">
                        <Plus className="size-4" />
                        Add user
                    </Button>
                </Link>
            }
        >
            <Head title="Users" />

            <div className="w-full space-y-4">
                <Card>
                    <CardContent className="flex flex-wrap items-end gap-3 p-3">
                        <div className="space-y-1">
                            <label htmlFor="q" className="text-muted-foreground text-xs">
                                Search
                            </label>
                            <Input
                                id="q"
                                className="w-auto"
                                defaultValue={filters.q}
                                onBlur={(event) => apply({ q: event.target.value || null })}
                                placeholder="Name or email…"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">Name</th>
                                    <th className="px-4 py-2 font-medium">Email</th>
                                    <th className="px-4 py-2 font-medium">Roles</th>
                                    <th className="px-4 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="text-muted-foreground px-4 py-12 text-center text-sm">
                                            No users yet.
                                        </td>
                                    </tr>
                                )}
                                {users.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="hover:bg-muted/30 cursor-pointer border-b last:border-0"
                                        onClick={() => router.get(`/users/${user.id}`)}
                                    >
                                        <td className="px-4 py-2">{user.name}</td>
                                        <td className="text-muted-foreground px-4 py-2">{user.email}</td>
                                        <td className="px-4 py-2">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.length === 0 ? (
                                                    <span className="text-muted-foreground">—</span>
                                                ) : (
                                                    user.roles.map((role) => (
                                                        <Badge key={role} variant="outline" className="capitalize">
                                                            {role}
                                                        </Badge>
                                                    ))
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-2">
                                            {user.is_active ? (
                                                <Badge>active</Badge>
                                            ) : (
                                                <Badge variant="outline">inactive</Badge>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}