import * as React from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * A styled native select: keyboard and mobile friendly for free, and it posts
 * a plain form value, which is all the Inertia forms need.
 */
function Select({
    className,
    children,
    ...props
}: React.ComponentProps<'select'>) {
    return (
        <div className="relative">
            <select
                data-slot="select"
                className={cn(
                    'h-9 w-full min-w-0 appearance-none rounded-4xl border border-input bg-input/30 pr-8 pl-3 text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 md:text-sm',
                    className,
                )}
                {...props}
            >
                {children}
            </select>
            <ChevronDown className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
        </div>
    );
}

export { Select };
