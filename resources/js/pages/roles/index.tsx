import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Role = {
    id: number;
    name: string;
    permission_count: number;
    user_count: number;
};

type Props = {
    roles: Role[];
};

export default function RolesIndex({ roles }: Props) {
    return (
        <AuthenticatedLayout
            title="Roles"
            description="Define what each type of user can do"
            actions={
                <Link href="/roles/create">
                    <Button size="sm">
                        <Plus className="size-4" />
                        Add role
                    </Button>
                </Link>
            }
        >
            <Head title="Roles" />

            <div className="w-full space-y-4">
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">Name</th>
                                    <th className="px-4 py-2 font-medium">Permissions</th>
                                    <th className="px-4 py-2 font-medium">Users</th>
                                </tr>
                            </thead>
                            <tbody>
                                {roles.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="text-muted-foreground px-4 py-12 text-center text-sm">
                                            No roles yet.
                                        </td>
                                    </tr>
                                )}
                                {roles.map((role) => (
                                    <tr
                                        key={role.id}
                                        className="hover:bg-muted/30 cursor-pointer border-b last:border-0 capitalize"
                                        onClick={() => router.get(`/roles/${role.id}`)}
                                    >
                                        <td className="px-4 py-2">{role.name}</td>
                                        <td className="text-muted-foreground px-4 py-2">
                                            {role.permission_count}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2">
                                            {role.user_count}
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