import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AuthenticatedLayout from '@/components/layouts/authenticated-layout';
import { BranchFormFields } from '@/components/branch/brach-formfield';

export default function CreateBranch() {
    return (
        <AuthenticatedLayout
            title="Add branch"
            description="Create a new branch for your company"
        >
            <Head title="Add branch" />

            <div className="max-w-full">
                <Link
                    href="/branches"
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to branches
                </Link>

                <Card>
                    <CardContent className="pt-4">
                        <Form action="/branches" method="post">
                            {({ errors, processing }) => (
                                <div className="space-y-6">
                                    <BranchFormFields
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
                                            ? 'Adding...'
                                            : 'Add branch'}
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