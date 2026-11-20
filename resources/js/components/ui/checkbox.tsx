import * as React from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type CheckboxProps = Omit<
    React.ComponentProps<'input'>,
    'type' | 'onChange'
> & {
    label: React.ReactNode;
    description?: React.ReactNode;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
};

/**
 * Controlled checkbox for the `useForm` pages, where the value is part of a
 * JSON payload and therefore always present, ticked or not.
 */
function Checkbox({
    label,
    description,
    checked,
    onCheckedChange,
    className,
    disabled,
    ...props
}: CheckboxProps) {
    return (
        <Label className={cn('items-start gap-2.5 font-normal', className)}>
            <input
                data-slot="checkbox"
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={(event) => onCheckedChange(event.target.checked)}
                className="mt-0.5 size-4 shrink-0 rounded-sm border-input bg-input/30 accent-primary disabled:cursor-not-allowed disabled:opacity-50"
                {...props}
            />
            <span className="leading-tight">
                <span className="block text-sm">{label}</span>
                {description && (
                    <span className="block text-xs text-muted-foreground">
                        {description}
                    </span>
                )}
            </span>
        </Label>
    );
}

export { Checkbox };
