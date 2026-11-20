import { Link, useForm } from "@inertiajs/react";
import { ArrowLeft, LoaderCircle, Save, Send } from "lucide-react";
import { useMemo, useState } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
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
import { Textarea } from "@/components/ui/textarea";
import { formatCents } from "@/lib/format";
import type {
    AccountGroup,
    AccountOption,
} from "@/components/journal/account-combobox";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from "@/components/ui/combobox";
import {
    emptyExpenseLine,
    ExpenseLinesEditor,
    expenseLinesTotals,
    type ExpenseLineInput,
} from "./expense-lines-editor";
import { ContactOption } from "../journal/journal-entry-form";
import type { BranchOption } from "@/types/purchases/form";

export type ExpenseFormData = {
    branch_id: string;
    contact_id: number | "";
    payee: string;
    expense_date: string;
    reference_no: string;
    description: string;
    notes: string;
    post_now: boolean;
    lines: ExpenseLineInput[];
};

export type ExpenseInitial = Partial<{
    branch_id: string | null;
    contact_id: number | null;
    expense_date: string;
    reference_no: string | null;
    payee: string | null;
    description: string | null;
    notes: string | null;
    lines: ExpenseLineInput[];
}>;

// Shared accounts (no "-suffix" in the code) stay available to every branch.
const belongsToBranch = (code: string, suffix: string) =>
    !code.includes("-") || code.endsWith(`-${suffix}`);

// Uses the explicit code when the server sends one, otherwise reads it from
// the start of the label (e.g. "1101-01 · Cash").
const codeOf = (option: AccountOption) =>
    option.code ?? option.label.split(/[\s·]/)[0];

export function ExpenseForm({
    action,
    method,
    branches,
    contacts,
    accountOptions,
    withholdingRates,
    vatRate,
    currency,
    initial,
    backHref,
    heading,
}: {
    action: string;
    method: "post" | "put";
    contacts: ContactOption[];
    branches: BranchOption[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    vatRate: number;
    currency: string;
    initial: ExpenseInitial;
    backHref: string;
    heading: { title: string; description?: string };
}) {
    const [lines, setLines] = useState<ExpenseLineInput[]>(
        initial.lines?.length ? initial.lines : [emptyExpenseLine()],
    );

    const form = useForm<ExpenseFormData>({
        branch_id: initial.branch_id ?? "",
        contact_id: initial.contact_id ?? "",
        expense_date: initial.expense_date ?? "",
        reference_no: initial.reference_no ?? "",
        payee: initial.payee ?? "",
        description: initial.description ?? "",
        notes: initial.notes ?? "",
        post_now: false,
        lines,
    });

    const { data, setData, processing, errors } = form;
    const errorsBag = errors as unknown as Record<string, string | undefined>;

    const totals = expenseLinesTotals(lines, vatRate);
    const selectedContact =
        contacts.find((contact) => contact.id === data.contact_id) ?? null;
    const preview = useMemo(
        () => ({
            ...totals,
            netPayable: totals.gross - totals.withholding,
        }),
        [totals],
    );

    const selectedBranch =
        branches.find(
            (branch) => String(branch.id) === String(data.branch_id),
        ) ?? null;
    const suffix = selectedBranch?.account_suffix ?? null;

    const accountCodeById = useMemo(() => {
        const map = new Map<number, string>();
        for (const group of accountOptions) {
            for (const option of group.options) {
                map.set(option.id, codeOf(option));
            }
        }
        return map;
    }, [accountOptions]);

    // Only the accounts that belong to the chosen branch.
    const branchAccounts = useMemo(() => {
        if (!suffix) return [];

        return accountOptions
            .map((group) => ({
                ...group,
                options: group.options.filter((option) =>
                    belongsToBranch(codeOf(option), suffix),
                ),
            }))
            .filter((group) => group.options.length > 0);
    }, [accountOptions, suffix]);

    const changeBranch = (branch: BranchOption | null) => {
        setData("branch_id", branch ? String(branch.id) : "");

        const nextSuffix = branch?.account_suffix ?? null;

        // Clear any line account that doesn't belong to the new branch.
        setLines((current) =>
            current.map((line) => {
                if (line.account_id === "") return line;

                const code = accountCodeById.get(line.account_id);

                return code && nextSuffix && belongsToBranch(code, nextSuffix)
                    ? line
                    : { ...line, account_id: "" };
            }),
        );
    };

    const submit = (postNow: boolean) => {
        form.transform((payload) => ({ ...payload, lines, post_now: postNow }));
        form[method === "put" ? "put" : "post"](action);
    };

    return (
        <AuthenticatedLayout
            title={heading.title}
            description={heading.description}
        >
            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
                <div className="space-y-4">
                    <Link
                        href={backHref}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to expenses
                    </Link>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit(false);
                        }}
                        className="space-y-4"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Expense details</CardTitle>
                                <CardDescription>
                                    What was spent, and what it covers
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Expense date"
                                    htmlFor="expense_date"
                                    error={errors.expense_date}
                                >
                                    <Input
                                        id="expense_date"
                                        type="date"
                                        value={data.expense_date}
                                        onChange={(event) =>
                                            setData(
                                                "expense_date",
                                                event.target.value,
                                            )
                                        }
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="Branch"
                                    htmlFor="branch_id"
                                    error={errors.branch_id}
                                    hint="Required. Accounts follow the branch you choose"
                                >
                                    <Combobox
                                        items={branches}
                                        value={selectedBranch}
                                        onValueChange={changeBranch}
                                        itemToStringLabel={(branch) =>
                                            branch
                                                ? `${branch.code} · ${branch.name}`
                                                : ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="Select a branch"
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
                                    hint="Pick a saved contact, or type a name below for a one-off payee"
                                >
                                    <Combobox
                                        items={contacts}
                                        value={selectedContact}
                                        onValueChange={(contact) => {
                                            setData(
                                                "contact_id",
                                                contact ? contact.id : "",
                                            );
                                        }}
                                        itemToStringLabel={(contact) =>
                                            contact?.name ?? ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="Choose a payee…"
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

                                {!selectedContact && (
                                    <Field
                                        label="Payee name (one-off)"
                                        htmlFor="payee"
                                        error={errors.payee}
                                    >
                                        <Input
                                            id="payee"
                                            value={data.payee}
                                            placeholder="Who was paid"
                                            onChange={(event) =>
                                                setData(
                                                    "payee",
                                                    event.target.value,
                                                )
                                            }
                                            disabled={processing}
                                        />
                                    </Field>
                                )}

                                {selectedContact && (
                                    <>
                                        <Field
                                            label="TIN"
                                            htmlFor="contact_tin"
                                        >
                                            <Input
                                                id="contact_tin"
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
                                            htmlFor="contact_address"
                                            className="sm:col-span-2"
                                        >
                                            <Input
                                                id="contact_address"
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
                                    label="Reference no."
                                    htmlFor="reference_no"
                                    error={errors.reference_no}
                                    hint="Receipt or invoice number"
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
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="Description"
                                    htmlFor="description"
                                    error={errors.description}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        id="description"
                                        value={data.description}
                                        placeholder="What this expense covers"
                                        onChange={(event) =>
                                            setData(
                                                "description",
                                                event.target.value,
                                            )
                                        }
                                        disabled={processing}
                                    />
                                </Field>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle>Line items</CardTitle>
                                    <CardDescription>
                                        VAT and withholding are computed per
                                        line
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {!selectedBranch && (
                                    <p className="text-red-300 mb-3 text-xs">
                                        Select a branch first to load its
                                        accounts.
                                    </p>
                                )}
                                {selectedBranch && !suffix && (
                                    <p className="text-destructive mb-3 text-xs">
                                        No account suffix is configured for{" "}
                                        {selectedBranch.name}, so no accounts
                                        can be loaded.
                                    </p>
                                )}
                                <ExpenseLinesEditor
                                    lines={lines}
                                    accounts={branchAccounts}
                                    errors={errorsBag}
                                    disabled={processing}
                                    currency={currency}
                                    vatRate={vatRate}
                                    withholdingRates={withholdingRates}
                                    onChange={setLines}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Notes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Textarea
                                    aria-label="Notes"
                                    rows={2}
                                    value={data.notes}
                                    onChange={(event) =>
                                        setData("notes", event.target.value)
                                    }
                                    disabled={processing}
                                />
                                {errors.notes && (
                                    <p className="text-destructive mt-1 text-xs">
                                        {errors.notes}
                                    </p>
                                )}
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
                            <Button
                                type="button"
                                disabled={processing}
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
                            </Button>
                            <Link href={backHref}>
                                <Button type="button" variant="ghost">
                                    Cancel
                                </Button>
                            </Link>
                        </div>
                    </form>
                </div>

                <aside className="space-y-4 xl:sticky xl:top-4 xl:self-start">
                    <Card>
                        <CardHeader>
                            <CardTitle>Breakdown</CardTitle>
                            <CardDescription>
                                Live preview of what will post
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row
                                label="Taxable amount"
                                value={formatCents(preview.taxable, currency)}
                            />
                            {preview.vat > 0 && (
                                <Row
                                    label="VAT"
                                    value={formatCents(preview.vat, currency)}
                                />
                            )}
                            <Row
                                label="Gross amount"
                                value={formatCents(preview.gross, currency)}
                            />
                            {preview.withholding > 0 && (
                                <Row
                                    label="Withholding tax"
                                    value={`(${formatCents(preview.withholding, currency)})`}
                                />
                            )}
                            <div className="flex items-center justify-between gap-2 border-t pt-2 font-medium">
                                <span>Net payable</span>
                                <span className="tabular-nums">
                                    {formatCents(preview.netPayable, currency)}
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                This expense is recorded to Accounts Payable and
                                settled later with a separate payment.
                            </p>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-2">
            <span className="text-muted-foreground">{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}