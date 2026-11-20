import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type SimpleSelectOption = {
    value: string;
    label: string;
};

export function SimpleSelect({
    options,
    value,
    onChange,
    placeholder = 'Select…',
    'aria-label': ariaLabel,
    className,
    disabled,
}: {
    options: SimpleSelectOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    'aria-label'?: string;
    className?: string;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    const selected = options.find((option) => option.value === value);

    useEffect(() => {
        if (!open) return;

        const handler = (event: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <button
                type="button"
                aria-label={ariaLabel}
                disabled={disabled}
                onClick={() => setOpen((value) => !value)}
                className={cn(
                    'border-input bg-background flex h-9 w-full items-center justify-between gap-2 rounded-full border px-3 text-sm shadow-xs',
                    'focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none',
                    disabled && 'cursor-not-allowed opacity-50',
                )}
            >
                <span className="truncate text-left">
                    {selected ? selected.label : placeholder}
                </span>
                <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
            </button>

            {open && (
                <div className="bg-popover text-popover-foreground absolute z-50 mt-1 w-full min-w-40 rounded-md border shadow-md">
                    <div className="max-h-64 overflow-y-auto p-1">
                        {options.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => {
                                    onChange(option.value);
                                    setOpen(false);
                                }}
                                className={cn(
                                    'hover:bg-accent hover:text-accent-foreground flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-left text-sm',
                                    option.value === value && 'bg-accent/50',
                                )}
                            >
                                <span className="truncate">{option.label}</span>
                                {option.value === value && (
                                    <Check className="size-4 shrink-0" />
                                )}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}