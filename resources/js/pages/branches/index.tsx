import { Head, Link, router } from "@inertiajs/react";
import { Building2, Pencil, Plus, Trash2 } from "lucide-react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import type { Branch } from "@/types/models";
import { create, destroy } from "@/routes/branches";
import { DeleteConfirmDialog } from "@/components/delete-dialog/remove";

export default function BranchesIndex({ branches }: { branches: Branch[] }) {
    const handleDelete = (branch: Branch) => {
        router.delete(destroy(branch.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            title="Branches"
            description="Locations that post their own journal entries and POS entries"
            actions={
                <Link
                    href={create()}
                    className={buttonVariants({ size: "sm" })}
                >
                    <Plus className="size-4" />
                    Add branch
                </Link>
            }
        >
            <Head title="Branches" />

            <Card>
                <CardContent className="p-0">
                    {branches.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-4 py-14 text-center">
                            <div className="flex size-9 items-center justify-center rounded-full bg-muted">
                                <Building2 className="size-4 text-muted-foreground" />
                            </div>
                            <p className="text-sm font-medium">
                                No branches yet
                            </p>
                            <p className="max-w-xs text-xs text-muted-foreground">
                                Add your first branch to start recording POS and
                                journal entries for it.
                            </p>
                            <Link
                                href="/branches/create"
                                className={cn(
                                    buttonVariants({ size: "sm" }),
                                    "mt-2",
                                )}
                            >
                                <Plus className="size-4" />
                                Add branch
                            </Link>
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs text-muted-foreground">
                                    <th className="px-4 py-2 font-medium">
                                        Code
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Contact
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {branches.map((branch) => (
                                    <tr
                                        key={branch.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {branch.code}
                                        </td>
                                        <td className="px-4 py-3">
                                            {branch.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {branch.phone || branch.email || (
                                                <span className="text-muted-foreground/60">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    branch.is_active
                                                        ? "default"
                                                        : "secondary"
                                                }
                                            >
                                                {branch.is_active
                                                    ? "Active"
                                                    : "Inactive"}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link
                                                    href={`/branches/${branch.id}/edit`}
                                                    className="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                                                    aria-label={`Edit ${branch.name}`}
                                                >
                                                    <Pencil className="size-3.5" />
                                                </Link>
                                                <DeleteConfirmDialog
                                                    title="Delete this branch?"
                                                    description={`This action cannot be undone. The branch "${branch.name}" will be permanently deleted.`}
                                                    confirmText="Delete branch"
                                                    onConfirm={() =>
                                                        handleDelete(branch)
                                                    }
                                                    trigger={
                                                        <button
                                                            type="button"
                                                            className="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                            aria-label={`Delete ${branch.name}`}
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </button>
                                                    }
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}
