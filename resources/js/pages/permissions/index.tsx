import { Head, Link, router } from "@inertiajs/react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ConfirmDeleteDialog } from "@/components/ui/confirm-delete-dialog";

type Permission = {
    id: number;
    name: string;
    label: string | null;
    role_count: number;
};

type Props = {
    permissions: Permission[];
};

export default function PermissionsIndex({ permissions }: Props) {
    const remove = (permission: Permission) => {
        if (!window.confirm(`Delete permission "${permission.name}"?`)) return;
        router.delete(`/permissions/${permission.id}`);
    };

    return (
        <AuthenticatedLayout
            title="Permissions"
            description="Capabilities that can be assigned to roles"
            actions={
                <Link href="/permissions/create">
                    <Button size="sm">
                        <Plus className="size-4" />
                        Add permission
                    </Button>
                </Link>
            }
        >
            <Head title="Permissions" />

            <div className="w-full space-y-4">
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Label
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Roles
                                    </th>
                                    <th className="w-20 px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {permissions.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="text-muted-foreground px-4 py-12 text-center text-sm"
                                        >
                                            No permissions yet.
                                        </td>
                                    </tr>
                                )}
                                {permissions.map((permission) => (
                                    <tr
                                        key={permission.id}
                                        className="hover:bg-muted/30 border-b last:border-0"
                                    >
                                        <td className="px-4 py-2 font-mono text-xs">
                                            {permission.name}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2">
                                            {permission.label ?? "—"}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-2">
                                            {permission.role_count}
                                        </td>
                                        <td className="px-4 py-2">
                                            <div className="flex items-center gap-1">
                                                <Link
                                                    href={`/permissions/${permission.id}/edit`}
                                                >
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label="Edit"
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </Button>
                                                </Link>
                                                <ConfirmDeleteDialog
                                                    title="Delete permission"
                                                    description={`Are you sure you want to delete "${permission.name}"? This cannot be undone.`}
                                                    onConfirm={() =>
                                                        router.delete(
                                                            `/permissions/${permission.id}`,
                                                        )
                                                    }
                                                />
                                            </div>
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
