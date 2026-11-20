import { Head } from '@inertiajs/react';
import { HeadingSmall } from '@/components/settings/heading-small';
import { SettingsLayout } from '@/components/settings/settings-layout';
import { ModeToggle } from '@/components/theme/mode-toggle';

export default function AppearanceSettings() {
    return (
        <SettingsLayout>
            <Head title="Appearance settings" />

            <div className="space-y-6">
                <HeadingSmall
                    title="Appearance"
                    description="Choose how the app looks"
                />

                <ModeToggle />
            </div>
        </SettingsLayout>
    );
}