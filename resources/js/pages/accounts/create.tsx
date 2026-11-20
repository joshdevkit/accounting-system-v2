import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import {
    AccountFormFields,
    type AccountFormData,
} from '@/components/account/account-form-fields';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AccountType, ParentOption } from '@/types/models';

type Props = {
    accountTypes: (AccountType & { id: number })[];
    parentOptions: ParentOption[];
    defaults: { parent_id: number | null; account_type_id: number | null };
};

export default function CreateAccount({
    accountTypes,
    parentOptions,
    defaults,
}: Props) {
    const { data, setData, post, processing, errors } =
        useForm<AccountFormData>({
            code: '',
            name: '',
            account_type_id: defaults.account_type_id
                ? String(defaults.account_type_id)
                : '',
            parent_id: defaults.parent_id ? String(defaults.parent_id) : '',
            description: '',
            is_control_account: false,
            is_active: true,
        });

    return (
        <AuthenticatedLayout
            title="Add account"
            description="New accounts are available to journal entries immediately"
        >
            <Head title="Add account" />

            <div className="max-w-full">
                <Link
                    href="/accounts"
                    className="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to chart of accounts
                </Link>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/accounts');
                    }}
                    className="space-y-4"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Account</CardTitle>
                            <CardDescription>
                                Give it a code that fits the numbering of its
                                parent
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <AccountFormFields
                                data={data}
                                errors={errors}
                                processing={processing}
                                accountTypes={accountTypes}
                                parentOptions={parentOptions}
                                onChange={(patch) =>
                                    setData({ ...data, ...patch })
                                }
                            />
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && (
                                <LoaderCircle className="size-4 animate-spin" />
                            )}
                            Add account
                        </Button>
                        <Link href="/accounts">
                            <Button type="button" variant="ghost">
                                Cancel
                            </Button>
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
