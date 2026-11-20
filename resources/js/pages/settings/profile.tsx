import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { HeadingSmall } from '@/components/settings/heading-small';
import { SettingsLayout } from '@/components/settings/settings-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

type Props = {
    user: {
        name: string;
        email: string;
        job_title: string | null;
        employee_id: string | null;
        phone: string | null;
        signature_name: string | null;
        avatar_url: string | null;
        signature_url: string | null;
        roles: string[];
    };
};

function initials(name: string) {
    return name
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export default function ProfileSettings({ user }: Props) {
    const [avatarPreview, setAvatarPreview] = useState<string | null>(user.avatar_url);
    const [signaturePreview, setSignaturePreview] = useState<string | null>(user.signature_url);
    const avatarInputRef = useRef<HTMLInputElement>(null);
    const signatureInputRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        name: user.name,
        email: user.email,
        job_title: user.job_title ?? '',
        employee_id: user.employee_id ?? '',
        phone: user.phone ?? '',
        signature_name: user.signature_name ?? '',
        avatar: null as File | null,
        signature: null as File | null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post('/settings/profile', { forceFormData: true });
    };

    const onAvatarChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('avatar', file);
        if (file) setAvatarPreview(URL.createObjectURL(file));
    };

    const onSignatureChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('signature', file);
        if (file) setSignaturePreview(URL.createObjectURL(file));
    };

    return (
        <SettingsLayout>
            <Head title="Profile settings" />

            <div className="space-y-8">
                <div className="space-y-6">
                    <HeadingSmall
                        title="Profile information"
                        description="Your identity across this system"
                    />

                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={() => avatarInputRef.current?.click()}
                            className="border-border bg-muted hover:border-primary/50 flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border text-lg font-medium transition-colors"
                        >
                            {avatarPreview ? (
                                <img src={avatarPreview} alt="" className="size-full object-contain" />
                            ) : (
                                initials(user.name)
                            )}
                        </button>
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => avatarInputRef.current?.click()}
                            >
                                Change photo
                            </Button>
                            <input
                                ref={avatarInputRef}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={onAvatarChange}
                            />
                            <p className="text-muted-foreground mt-1 text-xs">JPG or PNG, up to 2MB</p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-1.5">
                        {user.roles.map((role) => (
                            <Badge key={role} variant="outline" className="capitalize">
                                {role}
                            </Badge>
                        ))}
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Name" htmlFor="name" error={form.errors.name}>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    disabled={form.processing}
                                    autoComplete="name"
                                />
                            </Field>

                            <Field label="Email address" htmlFor="email" error={form.errors.email}>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    disabled={form.processing}
                                    autoComplete="username"
                                />
                            </Field>

                            <Field label="Job title" htmlFor="job_title" error={form.errors.job_title}>
                                <Input
                                    id="job_title"
                                    value={form.data.job_title}
                                    placeholder="e.g. Accountant"
                                    onChange={(event) => form.setData('job_title', event.target.value)}
                                    disabled={form.processing}
                                />
                            </Field>

                            <Field label="Employee ID" htmlFor="employee_id" error={form.errors.employee_id}>
                                <Input
                                    id="employee_id"
                                    value={form.data.employee_id}
                                    onChange={(event) => form.setData('employee_id', event.target.value)}
                                    disabled={form.processing}
                                />
                            </Field>

                            <Field label="Phone" htmlFor="phone" error={form.errors.phone}>
                                <Input
                                    id="phone"
                                    value={form.data.phone}
                                    onChange={(event) => form.setData('phone', event.target.value)}
                                    disabled={form.processing}
                                    autoComplete="tel"
                                />
                            </Field>
                        </div>

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                                Save
                            </Button>
                            {form.recentlySuccessful && (
                                <p className="text-muted-foreground text-sm">Saved</p>
                            )}
                        </div>
                    </form>
                </div>

                <div className="space-y-6 border-t pt-6">
                    <HeadingSmall
                        title="Signature"
                        description="Used on printed documents like check vouchers and reports"
                    />

                    <Field
                        label="Signature name"
                        htmlFor="signature_name"
                        error={form.errors.signature_name}
                        hint="How your name appears under a signature line, e.g. 'Juan D. Cruz'"
                    >
                        <Input
                            id="signature_name"
                            value={form.data.signature_name}
                            onChange={(event) => {
                                form.setData('signature_name', event.target.value);
                            }}
                            disabled={form.processing}
                        />
                    </Field>

                    <div className="flex items-center gap-4">
                        <div className="border-border bg-muted flex h-16 w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border">
                            {signaturePreview ? (
                                <img src={signaturePreview} alt="" className="max-h-full max-w-full object-contain" />
                            ) : (
                                <span className="text-muted-foreground text-xs">No signature</span>
                            )}
                        </div>
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => signatureInputRef.current?.click()}
                            >
                                Upload signature image
                            </Button>
                            <input
                                ref={signatureInputRef}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={onSignatureChange}
                            />
                            <p className="text-muted-foreground mt-1 text-xs">
                                A scanned or transparent PNG works best
                            </p>
                        </div>
                    </div>

                    <Button onClick={submit} disabled={form.processing}>
                        {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                        Save signature
                    </Button>
                </div>
            </div>
        </SettingsLayout>
    );
}