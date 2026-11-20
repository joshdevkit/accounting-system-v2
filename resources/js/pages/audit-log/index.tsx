import { Head, router } from '@inertiajs/react';
import { ChevronDown, Search, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import { formatDateTime } from '@/lib/format';
import { cn, compactQuery } from '@/lib/utils';
import type { PaginationLink } from '@/types/models';

type Change = {
    field: string;
    old: string | number | boolean | null;
    new: string | number | boolean | null;
};

type Log = {
    id: number;
    action: string;
    created_at: string | null;
    subject: string;
    user: {
        name: string;
        email: string;
    } | null;
    ip_address: string | null;
    changes: Change[];
};

type Props = {
    logs: Log[];
    pagination: PaginationLink[];
    filters: Record<string, unknown> & {
        action?: string;
        subject?: string;
        user_id?: number | null;
        from?: string;
        to?: string;
        q?: string;
    };
    subjects: {
        value: string;
        label: string;
    }[];
    actions: string[];
    users: {
        id: number;
        name: string;
        email: string;
    }[];
    stats: {
        total: number;
        today: number;
    };
};

const TITLES: Record<string, string> = {
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
    posted: 'Posted',
    reversed: 'Reversed',
    voided: 'Voided',
    locked: 'Locked',
    unlocked: 'Unlocked',
    restored: 'Restored',
};

function titleCase(action: string): string {
    return (
        TITLES[action] ??
        action
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (character) => character.toUpperCase())
    );
}

function renderValue(value: Change['old']): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

export default function AuditLogIndex({
    logs,
    pagination,
    filters,
    subjects,
    actions,
    users,
    stats,
}: Props) {
    const [open, setOpen] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.q ?? '');

    const apply = (next: Record<string, unknown>) => {
        router.get(
            '/audit-log',
            compactQuery({
                ...filters,
                ...next,
                page: undefined,
            }),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <AuthenticatedLayout
            title="Audit log"
            description="Every write to a company record — who did it, when, and what changed"
            actions={
                <Badge variant="outline" className="gap-1.5 py-1">
                    <ShieldCheck className="text-primary size-3.5" />
                    read-only
                </Badge>
            }
        >
            <Head title="Audit log" />

            <div className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        {
                            label: 'Events recorded',
                            value: stats.total,
                        },
                        {
                            label: 'Events today',
                            value: stats.today,
                        },
                        {
                            label: 'Record types',
                            value: subjects.length,
                        },
                        {
                            label: 'Users',
                            value: users.length,
                        },
                    ].map((item) => (
                        <div
                            key={item.label}
                            className="bg-card rounded-lg border px-4 py-3"
                        >
                            <p className="text-muted-foreground text-xs">
                                {item.label}
                            </p>

                            <p className="mt-0.5 text-lg font-semibold tabular-nums">
                                {item.value}
                            </p>
                        </div>
                    ))}
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-3 p-3">
                        <div className="relative min-w-52 flex-1">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />

                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        apply({
                                            q: search,
                                        });
                                    }
                                }}
                                placeholder="Search by record, value or IP"
                                className="pl-8"
                            />
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="action"
                                className="text-muted-foreground text-xs"
                            >
                                Action
                            </label>

                            <Select
                                id="action"
                                className="w-auto min-w-32"
                                value={filters.action ?? ''}
                                onChange={(event) =>
                                    apply({
                                        action:
                                            event.target.value || null,
                                    })
                                }
                            >
                                <option value="">All actions</option>

                                {actions.map((action) => (
                                    <option key={action} value={action}>
                                        {titleCase(action)}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="subject"
                                className="text-muted-foreground text-xs"
                            >
                                Record type
                            </label>

                            <Select
                                id="subject"
                                className="w-auto min-w-40"
                                value={filters.subject ?? ''}
                                onChange={(event) =>
                                    apply({
                                        subject:
                                            event.target.value || null,
                                    })
                                }
                            >
                                <option value="">All records</option>

                                {subjects.map((subject) => (
                                    <option
                                        key={subject.value}
                                        value={subject.value}
                                    >
                                        {subject.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="user_id"
                                className="text-muted-foreground text-xs"
                            >
                                User
                            </label>

                            <Select
                                id="user_id"
                                className="w-auto min-w-36"
                                value={
                                    filters.user_id
                                        ? String(filters.user_id)
                                        : ''
                                }
                                onChange={(event) =>
                                    apply({
                                        user_id: event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    })
                                }
                            >
                                <option value="">Everyone</option>

                                {users.map((user) => (
                                    <option
                                        key={user.id}
                                        value={String(user.id)}
                                    >
                                        {user.name}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="from"
                                className="text-muted-foreground text-xs"
                            >
                                From
                            </label>

                            <Input
                                id="from"
                                type="date"
                                className="w-auto"
                                defaultValue={filters.from ?? ''}
                                onBlur={(event) =>
                                    apply({
                                        from:
                                            event.target.value ||
                                            null,
                                    })
                                }
                            />
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="to"
                                className="text-muted-foreground text-xs"
                            >
                                To
                            </label>

                            <Input
                                id="to"
                                type="date"
                                className="w-auto"
                                defaultValue={filters.to ?? ''}
                                onBlur={(event) =>
                                    apply({
                                        to:
                                            event.target.value ||
                                            null,
                                    })
                                }
                            />
                        </div>

                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                apply({});
                            }}
                        >
                            Reset
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left text-xs">
                                    <th className="px-4 py-2 font-medium" />

                                    <th className="px-4 py-2 font-medium">
                                        When
                                    </th>

                                    <th className="px-4 py-2 font-medium">
                                        Action
                                    </th>

                                    <th className="px-4 py-2 font-medium">
                                        Record
                                    </th>

                                    <th className="px-4 py-2 font-medium">
                                        User
                                    </th>

                                    <th className="px-4 py-2 font-medium">
                                        IP
                                    </th>

                                    <th className="px-4 py-2 text-right font-medium">
                                        Changes
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {logs.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground px-4 py-12 text-center text-sm"
                                        >
                                            Nothing matches these filters.
                                        </td>
                                    </tr>
                                )}

                                {logs.map((log) => (
                                    <FragmentRow
                                        key={log.id}
                                        log={log}
                                        open={open === log.id}
                                        onToggle={() =>
                                            setOpen(
                                                open === log.id
                                                    ? null
                                                    : log.id,
                                            )
                                        }
                                    />
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Pagination links={pagination} />
            </div>
        </AuthenticatedLayout>
    );
}

function FragmentRow({
    log,
    open,
    onToggle,
}: {
    log: Log;
    open: boolean;
    onToggle: () => void;
}) {
    return (
        <>
            <tr
                onClick={onToggle}
                className={cn(
                    'hover:bg-muted/30 cursor-pointer border-b last:border-0',
                    open && 'bg-muted/30',
                )}
            >
                <td className="w-8 pr-0 pl-4">
                    <ChevronDown
                        className={cn(
                            'text-muted-foreground size-3.5 transition-transform',
                            !open && '-rotate-90',
                        )}
                    />
                </td>

                <td className="text-muted-foreground px-4 py-2 whitespace-nowrap">
                    {formatDateTime(log.created_at)}
                </td>

                <td className="px-4 py-2">
                    <StatusPill action={log.action} />
                </td>

                <td className="px-4 py-2 font-medium">
                    {log.subject}
                </td>

                <td className="px-4 py-2">
                    {log.user ? (
                        <span title={log.user.email}>
                            {log.user.name}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">
                            system
                        </span>
                    )}
                </td>

                <td className="text-muted-foreground px-4 py-2 font-mono text-xs">
                    {log.ip_address ?? '—'}
                </td>

                <td className="text-muted-foreground px-4 py-2 text-right tabular-nums">
                    {log.changes.length || '—'}
                </td>
            </tr>

            {open && (
                <tr className="bg-muted/20 border-b last:border-0">
                    <td />

                    <td
                        colSpan={6}
                        className="px-4 pt-0 pb-3"
                    >
                        {log.changes.length === 0 ? (
                            <p className="text-muted-foreground py-2 text-xs">
                                No field-level snapshot was stored for this
                                event.
                            </p>
                        ) : (
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="text-muted-foreground text-left">
                                        <th className="py-1 pr-3 font-medium">
                                            Change
                                        </th>

                                        <th className="py-1 pr-3 font-medium">
                                            Before
                                        </th>

                                        <th className="py-1 font-medium">
                                            After
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {log.changes.map((change) => (
                                        <tr
                                            key={change.field}
                                            className="border-border/40 border-t"
                                        >
                                            <td className="py-1 pr-3 font-medium">
                                                {change.field}
                                            </td>

                                            <td className="text-muted-foreground decoration-destructive/50 py-1 pr-3 line-through">
                                                {renderValue(
                                                    change.old,
                                                )}
                                            </td>

                                            <td className="py-1 font-medium">
                                                {renderValue(
                                                    change.new,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </td>
                </tr>
            )}
        </>
    );
}

function StatusPill({ action }: { action: string }) {
    const tone =
        action === 'created'
            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
            : action === 'deleted'
              ? 'bg-destructive/10 text-destructive'
              : action === 'updated'
                ? 'bg-primary/10 text-primary'
                : 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300';

    return (
        <span
            className={cn(
                'rounded-full px-2 py-0.5 text-xs font-medium',
                tone,
            )}
        >
            {titleCase(action)}
        </span>
    );
}