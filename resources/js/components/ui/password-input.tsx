import { Eye, EyeOff } from 'lucide-react';
import { forwardRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type PasswordInputProps = {
    id: string;
    value: string;
    onChange: (event: React.ChangeEvent<HTMLInputElement>) => void;
    disabled?: boolean;
    className?: string;
} & Omit<React.InputHTMLAttributes<HTMLInputElement>, 'id' | 'value' | 'onChange' | 'type'>;

export const PasswordInput = forwardRef<HTMLInputElement, PasswordInputProps>(
    ({ id, value, onChange, disabled, className, ...rest }, ref) => {
        const [visible, setVisible] = useState(false);

        return (
            <div className="relative">
                <Input
                    ref={ref}
                    id={id}
                    type={visible ? 'text' : 'password'}
                    value={value}
                    onChange={onChange}
                    disabled={disabled}
                    className={cn('pr-9', className)}
                    {...rest}
                />
                <button
                    type="button"
                    onClick={() => setVisible((current) => !current)}
                    tabIndex={-1}
                    aria-label={visible ? 'Hide password' : 'Show password'}
                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                >
                    {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                </button>
            </div>
        );
    },
);

PasswordInput.displayName = 'PasswordInput';