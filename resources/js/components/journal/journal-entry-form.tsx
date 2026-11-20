import { Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Info, LoaderCircle, Save, Send } from "lucide-react";
import { useState } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import {
    emptyLine,
    JournalLinesEditor,
    linesTotals,
} from "@/components/journal/journal-lines-editor";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { formatCents, formatDate } from "@/lib/format";
import type {
    AccountOption,
    BranchSummary,
    JournalLineInput,
    PeriodInfo,
} from "@/types/models";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from "../ui/combobox";

export type ContactOption = {
    id: number;
    name: string;
    tax_identification_number: string | null;
    address: string | null;
};

export type JournalEntryFormData = {
    reference_no: string;
    transaction_date: string;
    branch_id: string;
    contact_id: number | "";
    description: string;
    post_now: boolean;
    lines: JournalLineInput[];
};

export type JournalEntryFormErrors = Partial<
    Record<
        | keyof JournalEntryFormData
        | `lines.${number}.account_id`
        | `lines.${number}.debit`
        | `lines.${number}.credit`,
        string
    >
>;

export function JournalEntryForm({
    action,
    method,
    accounts,
    branches,
    contacts,
    period,
    currency,
    initial,
    backHref,
    heading,
}: {
    action: string;
    method: "post" | "put";
    accounts: AccountOption[];
    branches: BranchSummary[];
    contacts: ContactOption[];
    period: PeriodInfo | null;
    currency: string;
    initial: Partial<JournalEntryFormData> & {
        reference_no: string;
        transaction_date: string;
        description: string;
    };
    postingBlocked: boolean;
    backHref: string;
    heading: { title: string; description?: string };
}) {
    const [lines, setLines] = useState<JournalLineInput[]>(
        initial.lines?.length ? initial.lines : [emptyLine(), emptyLine()],
    );

    const form = useForm<JournalEntryFormData>({
        reference_no: initial.reference_no,
        transaction_date: initial.transaction_date,
        branch_id: initial.branch_id ?? "",
        contact_id: initial.contact_id ?? "",
        description: initial.description,
        post_now: false,
        lines,
    });

    const { data, setData, processing, errors } = form;
    const totals = linesTotals(lines);
    const selectedContact =
        contacts.find((contact) => contact.id === data.contact_id) ?? null;
    // Line-level errors arrive as `lines.0.account_id`, so the editor works
    // with a plain lookup bag.
    const errorsBag = errors as unknown as Record<string, string | undefined>;

    const submit = (postNow: boolean) => {
        const cleaned = lines.filter(
            (line) =>
                line.account_id !== "" ||
                (typeof line.debit === "string" ? line.debit !== "" : true) ||
                (typeof line.credit === "string" ? line.credit !== "" : true),
        );

        form.transform((payload) => ({
            ...payload,
            lines: cleaned,
            post_now: postNow,
        }));

        form[method === "put" ? "put" : "post"](action);
    };

    return (
        <AuthenticatedLayout
            title={heading.title}
            description={heading.description}
        >
            <div className="max-w-full space-y-4">
                <Link
                    href={backHref}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                >
                    <ArrowLeft className="size-3.5" />
                    Back to journal
                </Link>

                {accounts.length === 0 && (
                    <Alert variant="destructive">
                        <AlertTitle>Nothing to post to yet</AlertTitle>
                        <AlertDescription>
                            This company has no active accounts without
                            sub-accounts. Add some to the chart of accounts
                            first.
                        </AlertDescription>
                    </Alert>
                )}

                {period && !period.allows_posting && (
                    <Alert>
                        <Info className="size-4" />
                        <AlertTitle>
                            {period.name} is closed to posting
                        </AlertTitle>
                        <AlertDescription>
                            The draft is still filed here, but it cannot be
                            posted until the period — and its fiscal year{" "}
                            {period.fiscal_year?.year} — is reopened.
                        </AlertDescription>
                    </Alert>
                )}

                {!period && (
                    <Alert variant="destructive">
                        <AlertTitle>No fiscal year covers this date</AlertTitle>
                        <AlertDescription>
                            Entries must land in an accounting period, so create
                            the fiscal year first.
                        </AlertDescription>
                    </Alert>
                )}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(false);
                    }}
                    className="space-y-4"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Entry</CardTitle>
                            <CardDescription>
                                {period
                                    ? `Filed into ${period.name}, ${formatDate(period.start_date)} – ${formatDate(period.end_date)}`
                                    : "The period is derived from the date"}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
                                <Field
                                    label="Date"
                                    htmlFor="transaction_date"
                                    error={errors.transaction_date}
                                >
                                    <Input
                                        id="transaction_date"
                                        type="date"
                                        value={data.transaction_date}
                                        onChange={(event) =>
                                            setData(
                                                "transaction_date",
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.transaction_date}
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="Branch"
                                    htmlFor="branch_id"
                                    error={errors.branch_id}
                                    hint="Optional, for company-wide entries"
                                >
                                    <Combobox
                                        items={branches}
                                        value={
                                            branches.find(
                                                (branch) =>
                                                    String(branch.id) ===
                                                    data.branch_id,
                                            ) ?? null
                                        }
                                        onValueChange={(branch) => {
                                            setData(
                                                "branch_id",
                                                branch ? String(branch.id) : "",
                                            );
                                        }}
                                        itemToStringLabel={(branch) =>
                                            branch
                                                ? `${branch.code} · ${branch.name}`
                                                : ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="All branches"
                                            disabled={processing}
                                            className="w-full"
                                        />

                                        <ComboboxContent>
                                            <ComboboxList>
                                                {branches.map((branch) => (
                                                    <ComboboxItem
                                                        key={branch.id}
                                                        value={branch}
                                                    >
                                                        {branch.code} ·{" "}
                                                        {branch.name}
                                                    </ComboboxItem>
                                                ))}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </Field>

                                <Field
                                    label="Payee"
                                    htmlFor="contact_id"
                                    error={errors.contact_id}
                                    hint="Optional — vendor or customer this entry involves"
                                >
                                    <Combobox
                                        items={contacts}
                                        value={selectedContact}
                                        onValueChange={(contact) =>
                                            setData(
                                                "contact_id",
                                                contact ? contact.id : "",
                                            )
                                        }
                                        itemToStringLabel={(contact) =>
                                            contact?.name ?? ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="No payee"
                                            disabled={processing}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                {contacts.map((contact) => (
                                                    <ComboboxItem
                                                        key={contact.id}
                                                        value={contact}
                                                    >
                                                        {contact.name}
                                                    </ComboboxItem>
                                                ))}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </Field>

                                {selectedContact && (
                                    <>
                                        <Field label="TIN" htmlFor="payee_tin">
                                            <Input
                                                id="payee_tin"
                                                value={
                                                    selectedContact.tax_identification_number ??
                                                    "—"
                                                }
                                                disabled
                                                readOnly
                                            />
                                        </Field>
                                        <Field
                                            label="Address"
                                            htmlFor="payee_address"
                                        >
                                            <Input
                                                id="payee_address"
                                                value={
                                                    selectedContact.address ??
                                                    "—"
                                                }
                                                disabled
                                                readOnly
                                            />
                                        </Field>
                                    </>
                                )}

                                <Field
                                    label="Reference"
                                    htmlFor="reference_no"
                                    error={errors.reference_no}
                                    hint="Generated when blank"
                                >
                                    <Input
                                        id="reference_no"
                                        value={data.reference_no}
                                        onChange={(event) =>
                                            setData(
                                                "reference_no",
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.reference_no}
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="Description"
                                    htmlFor="description"
                                    error={errors.description}
                                >
                                    <Input
                                        id="description"
                                        value={data.description}
                                        placeholder="Monthly rent accrual"
                                        onChange={(event) =>
                                            setData(
                                                "description",
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.description}
                                        disabled={processing}
                                    />
                                </Field>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>Lines</CardTitle>
                                <CardDescription>
                                    Enter an amount on one side of each line
                                    only
                                </CardDescription>
                            </div>
                            <span className="text-muted-foreground text-xs">
                                {formatCents(totals.debits, currency)} debits ·{" "}
                                {formatCents(totals.credits, currency)} credits
                            </span>
                        </CardHeader>
                        <CardContent>
                            <JournalLinesEditor
                                lines={lines}
                                accounts={accounts}
                                errors={errorsBag}
                                disabled={processing}
                                currency={currency}
                                onChange={setLines}
                            />
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            <Save className="size-4" />
                            Save as draft
                        </Button>
                        {/* <Button
                            type="button"
                            disabled={
                                processing ||
                                postingBlocked ||
                                !period ||
                                accounts.length === 0 ||
                                totals.difference !== 0
                            }
                            onClick={() => submit(true)}
                        >
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Send className="size-4" />
                            )}
                            {method === "put"
                                ? "Update and post"
                                : "Save and post"}
                        </Button> */}
                        <Link href={backHref}>
                            <Button type="button" variant="ghost">
                                Cancel
                            </Button>
                        </Link>
                        {totals.difference !== 0 && (
                            <span className="text-destructive text-xs">
                                Out by{" "}
                                {formatCents(
                                    Math.abs(totals.difference),
                                    currency,
                                )}
                                — balance the entry to post it
                            </span>
                        )}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
