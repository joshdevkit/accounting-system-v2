import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * Label + control + validation message, so the many forms in this app fail in
 * the same readable way.
 */
export function Field({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: {
    label?: ReactNode;
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            {label && (
                <Label htmlFor={htmlFor} className="text-sm">
                    {label}
                </Label>
            )}
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

/**
 * Two-up grid used by most of the record forms.
 */
export function FieldGrid({
    columns = 2,
    className,
    children,
}: {
    columns?: 2 | 3 | 4;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'grid grid-cols-1 gap-4',
                columns === 2 && 'sm:grid-cols-2',
                columns === 3 && 'sm:grid-cols-3',
                columns === 4 && 'sm:grid-cols-2 xl:grid-cols-4',
                className,
            )}
        >
            {children}
        </div>
    );
}
