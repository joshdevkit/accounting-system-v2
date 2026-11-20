import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

const tones: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    info: 'bg-primary/10 text-primary',
    success: 'bg-primary/15 text-primary',
    warning: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    danger: 'bg-destructive/10 text-destructive',
};

const tonesFor: Record<string, Tone> = {
    draft: 'warning',
    posted: 'success',
    reversed: 'neutral',
    voided: 'danger',
    open: 'success',
    closed: 'neutral',
    active: 'success',
    inactive: 'neutral',
    manual: 'info',
    pos: 'info',
    created: 'info',
    updated: 'warning',
    deleted: 'danger',
    asset: 'info',
    liability: 'warning',
    equity: 'neutral',
    revenue: 'success',
    expense: 'danger',
    debit: 'info',
    credit: 'success',
    // payment status
    unpaid: 'warning',
    for_check: 'info',
    paid: 'success',
};

export function statusTone(value?: string | null): Tone {
    if (!value) return 'neutral';

    return tonesFor[value] ?? 'neutral';
}

export function StatusBadge({
    value,
    label,
    className,
}: {
    value?: string | null;
    label?: string;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'border-transparent capitalize',
                tones[statusTone(value)],
                className,
            )}
        >
            {label ?? value ?? '—'}
        </Badge>
    );
}
