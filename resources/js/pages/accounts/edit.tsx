import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import {
    AccountFormFields,
    type AccountFormData,
} from '@/components/account/account-form-fields';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AccountType, ParentOption } from '@/types/models';

type Account = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    account_type_id: number;
    parent_id: number | null;
    is_control_account: boolean;
    is_active: boolean;
};

type Props = {
    account: Account;
    accountTypes: (AccountType & { id: number })[];
    parentOptions: ParentOption[];
    usage: { children: number; lines: number };
};

export default function EditAccount({
    account,
    accountTypes,
    parentOptions,
    usage,
}: Props) {
    const { data, setData, put, processing, errors } = useForm<AccountFormData>(
        {
            code: account.code,
            name: account.name,
            account_type_id: String(account.account_type_id),
            parent_id: account.parent_id ? String(account.parent_id) : '',
            description: account.description ?? '',
            is_control_account: account.is_control_account,
            is_active: account.is_active,
        },
    );

    return (
        <AuthenticatedLayout
            title={`Edit ${account.code}`}
            description={account.name}
        >
            <Head title={`Edit ${account.name}`} />

            <div className="max-w-full space-y-4">
                <Link
                    href="/accounts"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to chart of accounts
                </Link>

                {usage.lines > 0 && (
                    <Alert>
                        <AlertTitle>
                            This account already has {usage.lines} journal{' '}
                            {usage.lines === 1 ? 'line' : 'lines'}
                        </AlertTitle>
                        <AlertDescription>
                            Renaming is safe; changing the account type is not,
                            and the ledger will refuse it. Deactivating keeps
                            history intact and only blocks new postings.
                        </AlertDescription>
                    </Alert>
                )}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        put(`/accounts/${account.id}`);
                    }}
                    className="space-y-4"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Account</CardTitle>
                            <CardDescription>
                                {usage.children > 0
                                    ? `${usage.children} sub-account${usage.children === 1 ? '' : 's'} roll up into this one`
                                    : 'No sub-accounts, so this account accepts postings'}
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
                            Save changes
                        </Button>
                        <Link href="/accounts">
                            <Button type="button" variant="ghost">
                                Cancel
                            </Button>
                        </Link>
                        <Link
                            href={`/reports/ledger?account=${account.id}`}
                            className="text-primary ml-auto text-sm hover:underline"
                        >
                            View account ledger
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
