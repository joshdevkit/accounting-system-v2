import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { cn } from '@/lib/utils';

const NAV_ITEMS = [
    { label: 'Profile', href: '/settings/profile' },
    { label: 'Password', href: '/settings/password' },
    { label: 'Appearance', href: '/settings/appearance' },
];

export function SettingsLayout({ children }: { children: ReactNode }) {
    const { url } = usePage();

    return (
        <AuthenticatedLayout title="Settings" description="Manage your profile and account settings">
            <div className="flex flex-col gap-6 lg:flex-row lg:gap-10">
                <aside className="lg:w-48">
                    <nav className="flex flex-row gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                        {NAV_ITEMS.map((item) => {
                            const active = url.startsWith(item.href);

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={cn(
                                        'shrink-0 rounded-md px-3 py-1.5 text-sm transition-colors',
                                        active
                                            ? 'bg-accent text-accent-foreground font-medium'
                                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                                    )}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="flex-1 lg:max-w-2xl">
                    <div className="space-y-8">{children}</div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}