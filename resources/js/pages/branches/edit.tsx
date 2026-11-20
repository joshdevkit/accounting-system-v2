import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { Branch } from '@/types/models';
import { BranchFormFields } from '@/components/branch/brach-formfield';
import branches from '@/routes/branches';

export default function EditBranch({ branch }: { branch: Branch }) {
    return (
        <AuthenticatedLayout
            title="Edit branch"
            description={branch.name}
        >
            <Head title={`Edit ${branch.name}`} />

            <div className="max-w-full">
                <Link
                    href={branches.index()}
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to branches
                </Link>

                <Card>
                    <CardContent className="pt-4">
                        <Form
                            action={`/branches/${branch.id}`}
                            method="put"
                        >
                            {({ errors, processing }) => (
                                <div className="space-y-6">
                                    <BranchFormFields
                                        branch={branch}
                                        errors={errors}
                                        processing={processing}
                                    />

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing && (
                                            <LoaderCircle className="size-4 animate-spin" />
                                        )}
                                        {processing
                                            ? 'Saving...'
                                            : 'Save changes'}
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}