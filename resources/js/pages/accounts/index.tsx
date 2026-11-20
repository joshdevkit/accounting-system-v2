import { Head, Link, router } from "@inertiajs/react";
import {
    Check,
    ChevronDown,
    ChevronRight,
    ChevronsUpDown,
    ListTree,
    Pencil,
    Plus,
    Power,
    Search,
} from "lucide-react";
import { Fragment, useMemo, useState } from "react";

import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { formatCentsSigned } from "@/lib/format";
import type { AccountRow, AccountType } from "@/types/models";
import { destroy, index } from "@/routes/accounts";
import { DeleteDialog } from "@/components/account/DeleteDialog";

type Props = {
    accounts: AccountRow[];
    categories: Pick<
        AccountType,
        "id" | "code" | "name" | "category" | "normal_balance"
    >[];
    stats: {
        total: number;
        postable: number;
        inactive: number;
    };
    filters: {
        q: string;
        category: string;
        status: string;
        unit: "maxi" | "gusto";
    };
};

type AccountSection = {
    key: string;
    title: string;
    description: string;
    categories: string[];
};

const ACCOUNT_SECTIONS: AccountSection[] = [
    {
        key: "financial-position",
        title: "Financial Position",
        description: "Assets, liabilities, and equity",
        categories: ["asset", "liability", "equity"],
    },
    {
        key: "income",
        title: "Income",
        description: "Revenue and other income",
        categories: ["income", "revenue"],
    },
    {
        key: "expenses",
        title: "Expenses",
        description: "Operating and other expenses",
        categories: ["expense", "expenses"],
    },
];

export default function AccountsIndex({
    accounts,
    categories,
    stats,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const [collapsedParents, setCollapsedParents] = useState<Set<number>>(
        () =>
            new Set(
                accounts
                    .filter((account) => account.depth === 0)
                    .map((account) => account.id),
            ),
    );

    const categoryOptions = Array.from(
        new Set(categories.map((type) => type.category)),
    );

    /*
     * Find the root parent for every account.
     *
     * Only depth === 0 accounts are accordions.
     * Everything underneath them stays expanded/collapsed
     * together with its root parent.
     */
    const rootParentByAccountId = useMemo(() => {
        const accountById = new Map(
            accounts.map((account) => [account.id, account]),
        );

        const result = new Map<number, number>();

        for (const account of accounts) {
            if (account.depth === 0) {
                result.set(account.id, account.id);
                continue;
            }

            let current = account;
            let root: AccountRow | undefined;

            while (current.parent) {
                const parent = accountById.get(current.parent.id);

                if (!parent) {
                    break;
                }

                current = parent;

                if (current.depth === 0) {
                    root = current;
                    break;
                }
            }

            if (root) {
                result.set(account.id, root.id);
            }
        }

        return result;
    }, [accounts]);

    const visible = useMemo(() => {
        return accounts.filter((account) => {
            /*
             * Root parents are always visible.
             */
            if (account.depth === 0) {
                return true;
            }

            /*
             * Hide descendants when their root parent is collapsed.
             */
            const rootParentId = rootParentByAccountId.get(account.id);

            if (!rootParentId) {
                return true;
            }

            return !collapsedParents.has(rootParentId);
        });
    }, [accounts, rootParentByAccountId, collapsedParents]);

    /*
     * Group the already-filtered accounts by financial statement section.
     *
     * We intentionally group after the Laravel filters have been applied
     * so search, category, status, and business-unit filtering continue
     * to behave exactly as before.
     */
    const groupedAccounts = useMemo(() => {
        const groups = new Map<string, AccountRow[]>();

        for (const section of ACCOUNT_SECTIONS) {
            groups.set(section.key, []);
        }

        for (const account of visible) {
            const category = account.account_type?.category?.toLowerCase();

            if (!category) {
                continue;
            }

            const section = ACCOUNT_SECTIONS.find((section) =>
                section.categories.includes(category),
            );

            if (section) {
                groups.get(section.key)?.push(account);
            }
        }

        return groups;
    }, [visible]);

    const toggleParent = (id: number) => {
        setCollapsedParents((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const applyFilters = (next: Partial<Props["filters"]>) => {
        const merged = {
            ...filters,
            ...next,
        };

        router.get(
            index(),
            Object.fromEntries(
                Object.entries(merged).filter(([, value]) => value !== ""),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const removeAccount = (account: AccountRow) => {
        router.delete(destroy(account.id), {
            preserveScroll: true,
        });
    };

    const renderAccountRows = (sectionAccounts: AccountRow[]) => {
        return sectionAccounts.map((account) => {
            const isRootParent = account.depth === 0;

            const isCollapsed =
                isRootParent && collapsedParents.has(account.id);

            return (
                <tr
                    key={account.id}
                    className={cn(
                        "hover:bg-muted/40 border-b last:border-0",
                        isRootParent && "bg-muted/20 font-medium",
                    )}
                >
                    <td className="px-4 py-2.5">
                        <div
                            className="flex min-w-0 flex-wrap items-center gap-2"
                            style={{
                                paddingLeft: `${account.depth * 1}rem`,
                            }}
                        >
                            {/* Parent accordion */}
                            {isRootParent ? (
                                <button
                                    type="button"
                                    onClick={() => toggleParent(account.id)}
                                    aria-label={
                                        isCollapsed
                                            ? `Expand ${account.name}`
                                            : `Collapse ${account.name}`
                                    }
                                    className="hover:bg-accent flex size-6 shrink-0 items-center justify-center rounded-md"
                                >
                                    {isCollapsed ? (
                                        <ChevronRight className="size-4" />
                                    ) : (
                                        <ChevronDown className="size-4" />
                                    )}
                                </button>
                            ) : (
                                /*
                                 * Keep indentation aligned with parent rows
                                 * without adding an accordion to sub-groups.
                                 */
                                <span className="size-6 shrink-0" />
                            )}

                            <Link
                                href={`/reports/ledger?account=${account.id}`}
                                className="min-w-0 break-words hover:underline"
                            >
                                <span className="text-muted-foreground font-mono text-xs">
                                    {account.code}
                                </span>{" "}
                                <span>{account.name}</span>
                            </Link>

                            {account.is_control_account && (
                                <Badge variant="outline">control</Badge>
                            )}

                            {!account.is_active && (
                                <StatusBadge value="inactive" />
                            )}

                            {account.children > 0 && (
                                <span className="text-muted-foreground text-xs">
                                    {account.children} sub
                                </span>
                            )}
                        </div>
                    </td>

                    <td className="text-muted-foreground px-4 py-2.5">
                        {account.account_type ? (
                            <StatusBadge
                                value={account.account_type.category}
                                label={account.account_type.name}
                                className="h-auto max-w-full whitespace-normal text-left"
                            />
                        ) : (
                            "—"
                        )}
                    </td>

                    <td className="text-muted-foreground px-4 py-2.5">
                        {account.is_postable ? (
                            <span className="text-primary text-xs">Yes</span>
                        ) : (
                            <span className="text-muted-foreground/70 text-xs">
                                {account.children > 0
                                    ? "Summary only"
                                    : "Inactive"}
                            </span>
                        )}
                    </td>

                    <td className="px-4 py-2.5 text-right tabular-nums">
                        {account.debit_cents
                            ? formatCentsSigned(account.debit_cents)
                            : "—"}
                    </td>

                    <td className="px-4 py-2.5 text-right tabular-nums">
                        {account.credit_cents
                            ? formatCentsSigned(account.credit_cents)
                            : "—"}
                    </td>

                    <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                        {account.lines > 0 ? (
                            <Link
                                href={`/reports/ledger?account=${account.id}`}
                                className="hover:underline"
                            >
                                {formatCentsSigned(account.balance_cents)}
                            </Link>
                        ) : (
                            "—"
                        )}
                    </td>

                    <td className="px-4 py-2.5">
                        <div className="flex items-center justify-end gap-1">
                            <button
                                type="button"
                                title={
                                    account.is_active
                                        ? "Deactivate"
                                        : "Activate"
                                }
                                onClick={() =>
                                    router.patch(
                                        `/accounts/${account.id}/active`,
                                        {
                                            is_active: !account.is_active,
                                        },
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                }
                                className="text-muted-foreground hover:bg-accent hover:text-foreground flex size-7 items-center justify-center rounded-md"
                            >
                                <Power className="size-3.5" />
                            </button>

                            <Link
                                href={`/accounts/${account.id}/edit`}
                                aria-label={`Edit ${account.name}`}
                                className="text-muted-foreground hover:bg-accent hover:text-foreground flex size-7 items-center justify-center rounded-md"
                            >
                                <Pencil className="size-3.5" />
                            </Link>

                            <DeleteDialog
                                account={account}
                                onConfirm={removeAccount}
                            />
                        </div>
                    </td>
                </tr>
            );
        });
    };

    const hasVisibleAccounts = ACCOUNT_SECTIONS.some(
        (section) => (groupedAccounts.get(section.key)?.length ?? 0) > 0,
    );

    return (
        <AuthenticatedLayout
            title="Chart of accounts"
            description="Accounts are per company, and only accounts without sub-accounts accept postings"
            actions={
                <Link
                    href="/accounts/create"
                    className={buttonVariants({ size: "sm" })}
                >
                    <Plus className="size-4" />
                    Add account
                </Link>
            }
        >
            <Head title="Chart of accounts" />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                {/* Business Unit Switch */}
                <div className="bg-muted flex items-center rounded-lg p-1">
                    <Button
                        type="button"
                        size="sm"
                        variant={filters.unit === "maxi" ? "default" : "ghost"}
                        className="h-8 rounded-md px-4"
                        onClick={() => applyFilters({ unit: "maxi" })}
                    >
                        Maxi
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        variant={filters.unit === "gusto" ? "default" : "ghost"}
                        className="h-8 rounded-md px-4"
                        onClick={() => applyFilters({ unit: "gusto" })}
                    >
                        Gusto
                    </Button>
                </div>

                {/* Search */}
                <form
                    className="flex min-w-56 flex-1 items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters({ q: search });
                    }}
                >
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />

                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by code or name"
                            aria-label="Search accounts"
                            className="pl-8"
                        />
                    </div>
                </form>

                {/* Category */}
                <Popover>
                    <PopoverTrigger
                        render={
                            <Button
                                variant="outline"
                                role="combobox"
                                className="w-auto min-w-40 justify-between"
                            />
                        }
                    >
                        {filters.category || "All categories"}

                        <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                    </PopoverTrigger>

                    <PopoverContent className="w-56 p-0">
                        <Command>
                            <CommandInput placeholder="Search category..." />

                            <CommandList>
                                <CommandEmpty>No category found.</CommandEmpty>

                                <CommandGroup>
                                    <CommandItem
                                        value="all categories"
                                        onSelect={() =>
                                            applyFilters({ category: "" })
                                        }
                                    >
                                        <Check
                                            className={
                                                filters.category === ""
                                                    ? "mr-2 size-4 opacity-100"
                                                    : "mr-2 size-4 opacity-0"
                                            }
                                        />
                                        All categories
                                    </CommandItem>

                                    {categoryOptions.map((category) => (
                                        <CommandItem
                                            key={category}
                                            value={category}
                                            onSelect={() =>
                                                applyFilters({ category })
                                            }
                                        >
                                            <Check
                                                className={
                                                    filters.category ===
                                                    category
                                                        ? "mr-2 size-4 opacity-100"
                                                        : "mr-2 size-4 opacity-0"
                                                }
                                            />

                                            {category}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>

                {/* Status */}
                <Popover>
                    <PopoverTrigger
                        render={
                            <Button
                                variant="outline"
                                role="combobox"
                                className="w-auto min-w-40 justify-between"
                            />
                        }
                    >
                        {filters.status === "inactive"
                            ? "Inactive only"
                            : "Active + inactive"}

                        <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                    </PopoverTrigger>

                    <PopoverContent className="w-56 p-0">
                        <Command>
                            <CommandGroup>
                                <CommandItem
                                    value="active + inactive"
                                    onSelect={() =>
                                        applyFilters({ status: "" })
                                    }
                                >
                                    <Check
                                        className={
                                            filters.status === ""
                                                ? "mr-2 size-4 opacity-100"
                                                : "mr-2 size-4 opacity-0"
                                        }
                                    />
                                    Active + inactive
                                </CommandItem>

                                <CommandItem
                                    value="inactive only"
                                    onSelect={() =>
                                        applyFilters({ status: "inactive" })
                                    }
                                >
                                    <Check
                                        className={
                                            filters.status === "inactive"
                                                ? "mr-2 size-4 opacity-100"
                                                : "mr-2 size-4 opacity-0"
                                        }
                                    />
                                    Inactive only
                                </CommandItem>
                            </CommandGroup>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>

            <Card>
                <CardContent className="p-0">
                    {!hasVisibleAccounts ? (
                        <div className="flex flex-col items-center gap-2 px-4 py-14 text-center">
                            <div className="bg-muted flex size-9 items-center justify-center rounded-full">
                                <ListTree className="text-muted-foreground size-4" />
                            </div>

                            <p className="text-sm font-medium">
                                {stats.total === 0
                                    ? "The chart of accounts is empty"
                                    : "Nothing matches these filters"}
                            </p>

                            <p className="text-muted-foreground max-w-sm text-xs">
                                {stats.total === 0
                                    ? "Seed the starter chart of accounts, or add accounts by hand. Tax and statutory accounts should be reviewed against your BIR treatment."
                                    : "Clear the search or the filters to see the rest of the accounts."}
                            </p>

                            {stats.total === 0 && (
                                <Link
                                    href="/accounts/create"
                                    className={cn(
                                        buttonVariants({ size: "sm" }),
                                        "mt-2",
                                    )}
                                >
                                    <Plus className="size-4" />
                                    Add the first account
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto xl:overflow-x-visible">
                            <table className="w-full min-w-250 table-fixed text-sm xl:min-w-0">
                                <thead>
                                    <tr className="text-muted-foreground border-b text-left text-xs">
                                        <th className="w-[31%] px-4 py-2 font-medium">
                                            Account
                                        </th>

                                        <th className="w-[14%] px-4 py-2 font-medium">
                                            Type
                                        </th>

                                        <th className="w-[10%] px-4 py-2 font-medium">
                                            Postable
                                        </th>

                                        <th className="w-[10%] px-4 py-2 text-right font-medium">
                                            Debits
                                        </th>

                                        <th className="w-[10%] px-4 py-2 text-right font-medium">
                                            Credits
                                        </th>

                                        <th className="w-[11%] px-4 py-2 text-right font-medium">
                                            Balance
                                        </th>

                                        <th className="w-[14%] px-4 py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {ACCOUNT_SECTIONS.map((section) => {
                                        const sectionAccounts =
                                            groupedAccounts.get(section.key) ??
                                            [];

                                        if (sectionAccounts.length === 0) {
                                            return null;
                                        }

                                        return (
                                            <Fragment key={section.key}>
                                                <tr className="bg-muted/30 border-y">
                                                    <td
                                                        colSpan={7}
                                                        className="px-4 py-3"
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <h2 className="text-sm font-semibold tracking-tight">
                                                                    {
                                                                        section.title
                                                                    }
                                                                </h2>

                                                                <p className="text-muted-foreground mt-0.5 text-xs">
                                                                    {
                                                                        section.description
                                                                    }
                                                                </p>
                                                            </div>

                                                            <span className="text-muted-foreground text-xs tabular-nums">
                                                                {
                                                                    sectionAccounts.length
                                                                }{" "}
                                                                accounts
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>

                                                {renderAccountRows(
                                                    sectionAccounts,
                                                )}
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <p className="text-muted-foreground mt-3 text-xs">
                {stats.total} accounts · {stats.postable} accepting postings ·{" "}
                {stats.inactive} inactive. Balances come from posted journal
                entries only, and summary accounts roll up their sub-accounts.
            </p>
        </AuthenticatedLayout>
    );
}
