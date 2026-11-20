import { Plus } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCents } from '@/lib/format';

type Aging = { total: number; current: number; overdue: number };

export function AgingCard({
    title,
    aging,
    currency,
    createHref,
}: {
    title: string;
    aging: Aging;
    currency: string;
    createHref: string;
}) {
    const currentPct = aging.total > 0 ? Math.round((aging.current / aging.total) * 100) : 0;

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
                <CardTitle className="text-base">{title}</CardTitle>
                <Link href={createHref} className="text-primary hover:underline">
                    <Plus className="size-4" />
                </Link>
            </CardHeader>
            <CardContent className="space-y-3">
                <p className="text-muted-foreground text-xs">
                    Total unpaid {formatCents(aging.total, currency)}
                </p>

                <div className="bg-muted flex h-2 overflow-hidden rounded-full">
                    <div
                        className="bg-primary h-full"
                        style={{ width: `${currentPct}%` }}
                    />
                    <div
                        className="h-full bg-amber-500"
                        style={{ width: `${100 - currentPct}%` }}
                    />
                </div>

                <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p className="text-primary text-xs font-medium">CURRENT</p>
                        <p className="font-semibold tabular-nums">
                            {formatCents(aging.current, currency)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-medium text-amber-600">OVERDUE</p>
                        <p className="font-semibold tabular-nums">
                            {formatCents(aging.overdue, currency)}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}