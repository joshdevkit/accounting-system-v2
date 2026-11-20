import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Branch } from '@/types/models';

type Errors = Partial<Record<'code' | 'name' | 'address' | 'phone' | 'email', string>>;

export function BranchFormFields({
    branch,
    errors,
    processing,
}: {
    branch?: Branch;
    errors: Errors;
    processing: boolean;
}) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="code">Branch code</Label>
                    <Input
                        id="code"
                        name="code"
                        placeholder="MAIN"
                        defaultValue={branch?.code}
                        aria-invalid={!!errors.code}
                        disabled={processing}
                        autoFocus
                    />
                    {errors.code && (
                        <p className="text-sm text-destructive">
                            {errors.code}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="name">Branch name</Label>
                    <Input
                        id="name"
                        name="name"
                        placeholder="Main branch"
                        defaultValue={branch?.name}
                        aria-invalid={!!errors.name}
                        disabled={processing}
                    />
                    {errors.name && (
                        <p className="text-sm text-destructive">
                            {errors.name}
                        </p>
                    )}
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="address">Address</Label>
                <Textarea
                    id="address"
                    name="address"
                    rows={3}
                    defaultValue={branch?.address ?? ''}
                    aria-invalid={!!errors.address}
                    disabled={processing}
                />
                {errors.address && (
                    <p className="text-sm text-destructive">
                        {errors.address}
                    </p>
                )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="phone">Phone</Label>
                    <Input
                        id="phone"
                        name="phone"
                        defaultValue={branch?.phone ?? ''}
                        aria-invalid={!!errors.phone}
                        disabled={processing}
                    />
                    {errors.phone && (
                        <p className="text-sm text-destructive">
                            {errors.phone}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        defaultValue={branch?.email ?? ''}
                        aria-invalid={!!errors.email}
                        disabled={processing}
                    />
                    {errors.email && (
                        <p className="text-sm text-destructive">
                            {errors.email}
                        </p>
                    )}
                </div>
            </div>

            {branch && (
                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    {/* Posted last-of-two so an unchecked box still deactivates
                        the branch instead of posting nothing at all. */}
                    <input type="hidden" name="is_active" value="0" />
                    <input
                        name="is_active"
                        type="checkbox"
                        value="1"
                        defaultChecked={branch.is_active}
                        className="size-4 rounded border-input accent-primary"
                        disabled={processing}
                    />
                    Active
                </label>
            )}
        </div>
    );
}