import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useRef } from 'react';
import { HeadingSmall } from '@/components/settings/heading-small';
import { SettingsLayout } from '@/components/settings/settings-layout';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { PasswordInput } from '@/components/ui/password-input';

type PasswordFormData = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

export default function PasswordSettings() {
    const currentPasswordRef = useRef<HTMLInputElement>(null);
    const passwordRef = useRef<HTMLInputElement>(null);

    const form = useForm<PasswordFormData>({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: (errors) => {
                if (errors.password) {
                    form.reset('password', 'password_confirmation');
                    passwordRef.current?.focus();
                }

                if (errors.current_password) {
                    form.reset('current_password');
                    currentPasswordRef.current?.focus();
                }
            },
        });
    };

    return (
        <SettingsLayout>
            <Head title="Password settings" />

            <div className="space-y-6">
                <HeadingSmall
                    title="Update password"
                    description="Use a long, random password to stay secure"
                />

                <form onSubmit={submit} className="space-y-4">
                    <Field
                        label="Current password"
                        htmlFor="current_password"
                        error={form.errors.current_password}
                    >
                        <PasswordInput
                            ref={currentPasswordRef}
                            id="current_password"
                            value={form.data.current_password}
                            onChange={(event) => form.setData('current_password', event.target.value)}
                            disabled={form.processing}
                            autoComplete="current-password"
                        />
                    </Field>

                    <Field label="New password" htmlFor="password" error={form.errors.password}>
                        <PasswordInput
                            ref={passwordRef}
                            id="password"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            disabled={form.processing}
                            autoComplete="new-password"
                        />
                    </Field>

                    <Field
                        label="Confirm password"
                        htmlFor="password_confirmation"
                        error={form.errors.password_confirmation}
                    >
                        <PasswordInput
                            id="password_confirmation"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData('password_confirmation', event.target.value)
                            }
                            disabled={form.processing}
                            autoComplete="new-password"
                        />
                    </Field>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                            Save password
                        </Button>

                        {form.recentlySuccessful && (
                            <p className="text-muted-foreground text-sm">Saved</p>
                        )}
                    </div>
                </form>
            </div>
        </SettingsLayout>
    );
}