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
    emptyPurchaseLine,
    PurchaseLinesEditor,
    purchaseLinesTotals,
    type PurchaseLineInput,
} from "./purchase-lines-editor";
import {
    BranchOption,
    ContactOption,
    PurchaseFormData,
    PurchaseInitial,
} from "@/types/purchases/form";

// Shared accounts (no "-suffix" in the code) stay available to every branch.
const belongsToBranch = (code: string, suffix: string) =>
    !code.includes("-") || code.endsWith(`-${suffix}`);

// Uses the explicit code when the server sends one, otherwise reads it from
// the start of the label (e.g. "1101-01 · Cash").
const codeOf = (option: AccountOption) =>
    option.code ?? option.label.split(/[\s·]/)[0];

export function PurchaseForm({
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
    branches: BranchOption[];
    contacts: ContactOption[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    vatRate: number;
    currency: string;
    initial: PurchaseInitial;
    backHref: string;
    heading: { title: string; description?: string };
}) {
    const [lines, setLines] = useState<PurchaseLineInput[]>(
        initial.lines?.length ? initial.lines : [emptyPurchaseLine()],
    );

    const form = useForm<PurchaseFormData>({
        branch_id: initial.branch_id ?? "",
        contact_id: initial.contact_id ?? "",
        vendor_name: initial.vendor_name ?? "",
        purchase_date: initial.purchase_date ?? "",
        due_date: initial.due_date ?? "",
        reference_no: initial.reference_no ?? "",
        description: initial.description ?? "",
        notes: initial.notes ?? "",
        post_now: false,
        lines,
        withholding_tax_rate: "0",
    });

    const { data, setData, processing, errors } = form;
    const errorsBag = errors as unknown as Record<string, string | undefined>;

    const selectedContact =
        contacts.find((contact) => contact.id === data.contact_id) ?? null;

    const totals = purchaseLinesTotals(lines, vatRate);

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
                        Back to purchases
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
                                <CardTitle>Purchase details</CardTitle>
                                <CardDescription>
                                    The bill this purchase is recording
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Purchase date"
                                    htmlFor="purchase_date"
                                    error={errors.purchase_date}
                                >
                                    <Input
                                        id="purchase_date"
                                        type="date"
                                        value={data.purchase_date}
                                        onChange={(event) =>
                                            setData(
                                                "purchase_date",
                                                event.target.value,
                                            )
                                        }
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="Due date"
                                    htmlFor="due_date"
                                    error={errors.due_date}
                                    hint="Defaults to the vendor's terms, if set"
                                >
                                    <Input
                                        id="due_date"
                                        type="date"
                                        value={data.due_date}
                                        onChange={(event) =>
                                            setData(
                                                "due_date",
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
                                    label="Vendor"
                                    htmlFor="contact_id"
                                    error={errors.contact_id}
                                    hint="Pick a saved contact, or type a name below for a one-off vendor"
                                >
                                    <Combobox
                                        items={contacts}
                                        value={selectedContact}
                                        onValueChange={(contact) => {
                                            setData(
                                                "contact_id",
                                                contact ? contact.id : "",
                                            );

                                            if (contact) {
                                                const base = data.purchase_date
                                                    ? new Date(
                                                          data.purchase_date,
                                                      )
                                                    : new Date();
                                                base.setDate(
                                                    base.getDate() +
                                                        contact.default_payment_terms_days,
                                                );
                                                setData(
                                                    "due_date",
                                                    base
                                                        .toISOString()
                                                        .slice(0, 10),
                                                );
                                            }
                                        }}
                                        itemToStringLabel={(contact) =>
                                            contact?.name ?? ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="Choose a vendor…"
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
                                        label="Vendor name (one-off)"
                                        htmlFor="vendor_name"
                                        error={errors.vendor_name}
                                    >
                                        <Input
                                            id="vendor_name"
                                            value={data.vendor_name}
                                            placeholder="Supplier name"
                                            onChange={(event) =>
                                                setData(
                                                    "vendor_name",
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
                                    hint="Bill or invoice number"
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
                                        placeholder="What this bill covers"
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
                                        What was purchased — VAT and withholding
                                        are computed per line
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
                                <PurchaseLinesEditor
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
                                    ? "Update and review journal"
                                    : "Save and review journal"}
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
                                This purchase is recorded to Accounts Payable
                                and settled later with a separate payment.
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
