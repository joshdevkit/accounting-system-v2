import { Link, useForm } from "@inertiajs/react";
import { ArrowLeft, LoaderCircle, Save, Send } from "lucide-react";
import { useMemo } from "react";
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
import { amountToCents, formatCents } from "@/lib/format";
import { AccountCombobox, AccountGroup } from "../journal/account-combobox";
import {
    Combobox,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from "@/components/ui/combobox";

export type SaleFormData = {
    branch_id: string;
    sale_date: string;
    due_date: string;
    credit_account_id: number | "";
    reference_no: string;
    customer_name: string;
    taxable_amount: string;
    tax_type: "vatable" | "non_vat" | "vat_exempt";
    business_source: string;
    discount_type: string;
    withholding_tax_rate: string;
    notes: string;
    post_now: boolean;
};

export type SaleInitial = {
    branch_id?: string | null;
    sale_date: string;
    due_date?: string | null;
    credit_account_id?: number | null;
    reference_no?: string | null;
    customer_name?: string | null;
    taxable_amount?: number | string | null;
    tax_type?: "vatable" | "non_vat" | "vat_exempt";
    business_source?: string | null;
    discount_type?: string | null;
    withholding_tax_rate?: number | string | null;
    notes?: string | null;
};

const TAX_TYPES: { value: "vatable" | "non_vat" | "vat_exempt"; label: string }[] = [
    { value: "non_vat", label: "Non-VAT" },
    { value: "vatable", label: "VATable" },
    { value: "vat_exempt", label: "VAT-exempt" },
];

const BUSINESS_SOURCES = [
    { value: "individual", label: "Individual" },
    { value: "government", label: "Government" },
    { value: "corporate", label: "Corporate" },
    { value: "website_online_booking", label: "Website/Online Booking" },
] as const;

const DISCOUNT_TYPES = [
    { value: "none", label: "No Discount" },
    { value: "senior", label: "20% - Senior Citizen" },
    { value: "employee", label: "25% - Employee" },
    { value: "pwd", label: "20% - PWD" },
] as const;

export function SaleForm({
    action,
    method,
    branches,
    accountOptions,
    withholdingRates,
    businessSources,
    discounts,
    vatRate,
    currency,
    initial,
    backHref,
    heading,
}: {
    action: string;
    method: "post" | "put";
    branches: { id: number; name: string; code: string }[];
    accountOptions: AccountGroup[];
    withholdingRates: { value: string; label: string }[];
    businessSources: { value: string; label: string }[];
    discounts: { value: string; label: string }[];
    vatRate: number;
    currency: string;
    initial: SaleInitial;
    backHref: string;
    heading: { title: string; description?: string };
}) {
    const form = useForm<SaleFormData>({
        branch_id: initial.branch_id ?? "",
        sale_date: initial.sale_date,
        due_date: initial.due_date ?? "",
        credit_account_id: initial.credit_account_id ?? "",
        reference_no: initial.reference_no ?? "",
        customer_name: initial.customer_name ?? "",
        taxable_amount:
            initial.taxable_amount === undefined ||
            initial.taxable_amount === null
                ? ""
                : String(initial.taxable_amount),
        tax_type: initial.tax_type ?? "non_vat",
        business_source: (initial.business_source as string) ?? "",
        discount_type: (initial.discount_type as string) ?? "none",
        withholding_tax_rate:
            initial.withholding_tax_rate === undefined ||
            initial.withholding_tax_rate === null
                ? "0"
                : String(initial.withholding_tax_rate),
        notes: initial.notes ?? "",
        post_now: false,
    });

    const { data, setData, processing, errors } = form;

    const selectedBranch =
        branches.find(
            (branch) => String(branch.id) === String(data.branch_id),
        ) ?? null;

    const selectedTaxType =
        TAX_TYPES.find((type) => type.value === data.tax_type) ?? null;

    const selectedBusinessSource =
        businessSources.find(
            (source) => source.value === data.business_source,
        ) ?? null;

    const selectedDiscountType =
        discounts.find((type) => type.value === data.discount_type) ?? null;

    const selectedWithholdingRate =
        withholdingRates.find(
            (rate) => rate.value === data.withholding_tax_rate,
        ) ?? null;

    const preview = useMemo(() => {
        const taxable = amountToCents(data.taxable_amount);
        const vat =
            data.tax_type === "vatable" ? Math.round(taxable * vatRate) : 0;
        const gross = taxable + vat;
        const withholdingRate = Number(data.withholding_tax_rate || 0);
        const withholding =
            withholdingRate > 0
                ? Math.round(taxable * (withholdingRate / 100))
                : 0;
        const netReceivable = gross - withholding;

        return { taxable, vat, gross, withholding, netReceivable };
    }, [
        data.taxable_amount,
        data.tax_type,
        data.withholding_tax_rate,
        vatRate,
    ]);

    const submit = (postNow: boolean) => {
        form.transform((payload) => ({ ...payload, post_now: postNow }));
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
                        Back to sales
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
                                <CardTitle>Sale details</CardTitle>
                                <CardDescription>
                                    The invoice this sale is recording
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Sale date"
                                    htmlFor="sale_date"
                                    error={errors.sale_date}
                                >
                                    <Input
                                        id="sale_date"
                                        type="date"
                                        value={data.sale_date}
                                        onChange={(event) =>
                                            setData(
                                                "sale_date",
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
                                    hint="Defaults to 30 days after the sale date"
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
                                    hint="Optional"
                                >
                                    <Combobox
                                        items={branches}
                                        value={selectedBranch}
                                        onValueChange={(branch) =>
                                            setData(
                                                "branch_id",
                                                branch ? String(branch.id) : "",
                                            )
                                        }
                                        itemToStringLabel={(branch) =>
                                            branch
                                                ? `${branch.code} · ${branch.name}`
                                                : ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="No specific branch"
                                            disabled={processing}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                <ComboboxItem value={null}>
                                                    No specific branch
                                                </ComboboxItem>
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
                                    label="Customer"
                                    htmlFor="customer_name"
                                    error={errors.customer_name}
                                >
                                    <Input
                                        id="customer_name"
                                        value={data.customer_name}
                                        placeholder="Customer or client name"
                                        onChange={(event) =>
                                            setData(
                                                "customer_name",
                                                event.target.value,
                                            )
                                        }
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="Reference no."
                                    htmlFor="reference_no"
                                    error={errors.reference_no}
                                    hint="Invoice number"
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
                                    label="Revenue account"
                                    htmlFor="credit_account_id"
                                    error={errors.credit_account_id}
                                    className="sm:col-span-2"
                                    hint="What this sale is for"
                                >
                                    <AccountCombobox
                                        aria-label="Revenue account"
                                        value={data.credit_account_id}
                                        disabled={processing}
                                        groups={accountOptions}
                                        onChange={(value) =>
                                            setData("credit_account_id", value)
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Taxable amount"
                                    htmlFor="taxable_amount"
                                    error={errors.taxable_amount}
                                    hint="Before VAT"
                                >
                                    <Input
                                        id="taxable_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        className="text-right tabular-nums"
                                        value={data.taxable_amount}
                                        onChange={(event) =>
                                            setData(
                                                "taxable_amount",
                                                event.target.value,
                                            )
                                        }
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="Tax type"
                                    htmlFor="tax_type"
                                    error={errors.tax_type}
                                >
                                    <Combobox
                                        items={TAX_TYPES}
                                        value={selectedTaxType}
                                        onValueChange={(type) =>
                                            setData(
                                                "tax_type",
                                                type
                                                    ? (type.value as SaleFormData["tax_type"])
                                                    : ("non_vat" as SaleFormData["tax_type"]),
                                            )
                                        }
                                        itemToStringLabel={(type) =>
                                            type?.label ?? ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="Select tax type"
                                            disabled={processing}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                {TAX_TYPES.map((type) => (
                                                    <ComboboxItem
                                                        key={type.value}
                                                        value={type}
                                                    >
                                                        {type.label}
                                                    </ComboboxItem>
                                                ))}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </Field>

                                <Field
                                    label="Business source"
                                    htmlFor="business_source"
                                    error={errors.business_source}
                                    hint="Where this sale originated"
                                >
                                    <Combobox
                                        items={BUSINESS_SOURCES}
                                        value={selectedBusinessSource}
                                        onValueChange={(source) =>
                                            setData(
                                                "business_source",
                                                source
                                                    ? (source.value as SaleFormData["business_source"])
                                                    : "",
                                            )
                                        }
                                        itemToStringLabel={(source) =>
                                            source?.label ?? ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="Select business source"
                                            disabled={processing}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                {BUSINESS_SOURCES.map(
                                                    (source) => (
                                                        <ComboboxItem
                                                            key={source.value}
                                                            value={source}
                                                        >
                                                            {source.label}
                                                        </ComboboxItem>
                                                    ),
                                                )}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </Field>

                                <Field
                                    label="Discount"
                                    htmlFor="discount_type"
                                    error={errors.discount_type}
                                    hint="Apply any customer discount"
                                >
                                    <Combobox
                                        items={DISCOUNT_TYPES}
                                        value={selectedDiscountType}
                                        onValueChange={(type) =>
                                            setData(
                                                "discount_type",
                                                type ? type.value : "none",
                                            )
                                        }
                                        itemToStringLabel={(type) =>
                                            type?.label ?? ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="Select discount"
                                            disabled={processing}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                {DISCOUNT_TYPES.map((type) => (
                                                    <ComboboxItem
                                                        key={type.value}
                                                        value={type}
                                                    >
                                                        {type.label}
                                                    </ComboboxItem>
                                                ))}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </Field>

                                <Field
                                    label="Withholding tax"
                                    htmlFor="withholding_tax_rate"
                                    error={errors.withholding_tax_rate}
                                    hint="Withheld by the customer, if any"
                                >
                                    <Combobox
                                        items={withholdingRates}
                                        value={selectedWithholdingRate}
                                        onValueChange={(rate) =>
                                            setData(
                                                "withholding_tax_rate",
                                                rate ? rate.value : "0",
                                            )
                                        }
                                        itemToStringLabel={(rate) =>
                                            rate?.label ?? ""
                                        }
                                    >
                                        <ComboboxInput
                                            placeholder="Select withholding rate"
                                            disabled={processing}
                                            className="w-full"
                                        />
                                        <ComboboxContent>
                                            <ComboboxList>
                                                {withholdingRates.map(
                                                    (rate) => (
                                                        <ComboboxItem
                                                            key={rate.value}
                                                            value={rate}
                                                        >
                                                            {rate.label}
                                                        </ComboboxItem>
                                                    ),
                                                )}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </Field>
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
                            {data.tax_type === "vatable" && (
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
                                <span>Net receivable</span>
                                <span className="tabular-nums">
                                    {formatCents(
                                        preview.netReceivable,
                                        currency,
                                    )}
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                This sale is recorded to Accounts Receivable and
                                collected later with a separate payment.
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
