import { Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    Check,
    ChevronsUpDown,
    LoaderCircle,
    Save,
    Send,
    Wand2,
} from "lucide-react";
import { useMemo, useState } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
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
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { Textarea } from "@/components/ui/textarea";
import { buildPostingPreview } from "@/lib/pos-preview";
import { amountToCents, formatCents, formatCentsSigned } from "@/lib/format";
import type { PosPostingMappingRow } from "@/types/models";

export const POS_INCOME_FIELDS = [
    { name: "basic_room_rate_amount", label: "Basic room rates" },
    { name: "extra_bed_amount", label: "Extra bed" },
    { name: "room_upgrade_amount", label: "Room upgrade" },
    { name: "no_show_amount", label: "No show" },
    { name: "early_check_in_out_amount", label: "Early check-in/check-out" },
    { name: "spa_amount", label: "SPA" },
    { name: "laundry_amount", label: "Laundry bag & laundry services" },
    { name: "shuttle_amount", label: "Shuttle services" },
    { name: "smoking_fee_amount", label: "Smoking fee" },
    { name: "key_card_amount", label: "Key card" },
    { name: "amenities_amount", label: "Amenities (pillows, robes, etc)" },
    { name: "avl_amount", label: "AVL (audio, visual, and lighting)" },
    {
        name: "other_income_amount",
        label: "Others (flowers, slippers, cake, chocolates, balloons, etc)",
    },
] as const;

const detailFields = [
    { name: "guest_name", label: "Guest name", type: "text" },
    { name: "address", label: "Address", type: "text" },
    { name: "tin", label: "TIN", type: "text" },
    { name: "arrival", label: "Arrival", type: "datetime-local" },
    { name: "departure", label: "Departure", type: "datetime-local" },
    { name: "avl_increment_amount", label: "AVL increment", type: "number" },
    {
        name: "other_suppliers_increment_amount",
        label: "Other suppliers increment",
        type: "number",
    },
    {
        name: "massage_increment_amount",
        label: "Massage services increment",
        type: "number",
    },
    {
        name: "laundry_increment_amount",
        label: "Laundry services increment",
        type: "number",
    },
    {
        name: "key_card_increment_amount",
        label: "Key card increment",
        type: "number",
    },
    {
        name: "amenities_increment_amount",
        label: "Amenities increment",
        type: "number",
    },
] as const;

const editableDiscountTypes = ["promo", "special_open", "corporate_government"];

export type PosSalesDetails = Partial<
    Record<
        | (typeof detailFields)[number]["name"]
        | "discount_rate"
        | "business_source"
        | "vat_classification"
        | "discount_type"
        | "withholding_tax_rate",
        string | null
    >
>;

export type PosSalesOptions = {
    businessSources: Record<string, string>;
    vatClassifications: Record<string, string>;
    discountTypes: Record<string, string>;
    discountRates: Record<string, number>;
    withholdingTaxRates: number[];
};

export const POS_SALES_FIELDS = [
    {
        name: "complimentary_room_amount",
        label: "Complementary room (no charge)",
    },
    { name: "gross_sales", label: "Gross sales" },
    { name: "discount_amount", label: "Discounts" },
    { name: "net_sales", label: "Net sales" },
] as const;

export const POS_TAX_FIELDS = [
    { name: "withholding_tax_amount", label: "Withholding tax amount" },
    { name: "taxable_amount", label: "Taxable sales" },
    { name: "exempted_amount", label: "VAT-exempt sales" },
    { name: "vat_amount", label: "VAT" },
    { name: "local_tax_amount", label: "Local tax" },
    { name: "service_charge_amount", label: "Service charge" },
] as const;

export const POS_TENDER_FIELDS = [
    { name: "online_booking_amount", label: "Online booking" },
    { name: "cash_amount", label: "Cash" },
    { name: "card_amount", label: "Card" },
    { name: "gcash_qrph_amount", label: "GCash / QRPh" },
    {
        name: "bank_transfer_amount",
        label: "Bank transfer, QR Ph, online booking",
    },
    { name: "cheque_amount", label: "Cheque" },
    { name: "accounts_receivable_amount", label: "Send bill" },
    { name: "room_post_amount", label: "Room post" },
    { name: "gift_voucher_amount", label: "Gift voucher" },
    { name: "other_payment_amount", label: "Other payment" },
] as const;

export type PosAmountField =
    | (typeof POS_INCOME_FIELDS)[number]["name"]
    | (typeof POS_SALES_FIELDS)[number]["name"]
    | (typeof POS_TAX_FIELDS)[number]["name"]
    | (typeof POS_TENDER_FIELDS)[number]["name"];

export type PosFormData = {
    calculation_version: string;
    branch_id: string;
    business_date: string;
    pos_reference: string;
    notes: string;
    post_now: boolean;
} & Record<keyof PosSalesDetails, string> &
    Record<PosAmountField, string>;

export type PosEntryInitial = {
    calculation_version?: string | null;
    branch_id: string;
    business_date: string;
    pos_reference: string;
    notes?: string | null;
    post_now?: boolean;
} & PosSalesDetails &
    Partial<Record<PosAmountField, string | number | null>>;

const allFields: PosAmountField[] = [
    ...POS_INCOME_FIELDS.map((field) => field.name),
    ...POS_SALES_FIELDS.map((field) => field.name),
    ...POS_TAX_FIELDS.map((field) => field.name),
    ...POS_TENDER_FIELDS.map((field) => field.name),
] as PosAmountField[];

export function emptyAmounts(): Record<PosAmountField, string> {
    return Object.fromEntries(allFields.map((field) => [field, ""])) as Record<
        PosAmountField,
        string
    >;
}

export function PosEntryForm({
    action,
    method,
    branches,
    mapping: defaultMapping,
    mappingByBranch,
    salesOptions,
    initial,
    currency,
    backHref,
    heading,
}: {
    action: string;
    method: "post" | "put";
    branches: { id: number; name: string; code: string }[];
    mapping: PosPostingMappingRow[];
    mappingByBranch: Record<string, PosPostingMappingRow[]>;
    salesOptions: PosSalesOptions;
    initial: PosEntryInitial;
    currency: string;
    backHref: string;
    heading: { title: string; description?: string };
}) {
    const form = useForm<PosFormData>({
        calculation_version:
            initial.calculation_version ??
            (method === "post" ? "sales_format_4" : ""),
        branch_id: initial.branch_id ?? "",
        business_date: initial.business_date ?? "",
        pos_reference: initial.pos_reference ?? "",
        notes: initial.notes ?? "",
        post_now: false,
        guest_name: initial.guest_name ?? "",
        address: initial.address ?? "",
        tin: initial.tin ?? "",
        business_source: initial.business_source ?? "",
        vat_classification: initial.vat_classification ?? "",
        discount_type: initial.discount_type ?? "",
        arrival: initial.arrival ?? "",
        departure: initial.departure ?? "",
        discount_rate: initial.discount_rate ?? "",
        avl_increment_amount: initial.avl_increment_amount ?? "",
        other_suppliers_increment_amount:
            initial.other_suppliers_increment_amount ?? "",
        massage_increment_amount: initial.massage_increment_amount ?? "",
        laundry_increment_amount: initial.laundry_increment_amount ?? "",
        key_card_increment_amount: initial.key_card_increment_amount ?? "",
        amenities_increment_amount: initial.amenities_increment_amount ?? "",
        withholding_tax_rate:
            initial.withholding_tax_rate == null ||
            initial.withholding_tax_rate === ""
                ? ""
                : String(Number(initial.withholding_tax_rate)),
        ...emptyAmounts(),
        ...(Object.fromEntries(
            allFields.map((field) => [
                field,
                initial[field] === undefined || initial[field] === null
                    ? ""
                    : String(initial[field]),
            ]),
        ) as Record<PosAmountField, string>),
    });

    const { data, setData, processing, errors } = form;
    const mapping = mappingByBranch[data.branch_id] ?? defaultMapping;
    const incomeTotal = POS_INCOME_FIELDS.reduce(
        (total, field) => total + amountToCents(data[field.name]),
        0,
    );
    const hasIncomeDetails =
        ["sales_format_2", "sales_format_3", "sales_format_4"].includes(
            data.calculation_version,
        ) ||
        incomeTotal !== 0 ||
        amountToCents(data.complimentary_room_amount) !== 0;
    const [netTouched, setNetTouched] = useState(
        () => Number(initial.net_sales ?? 0) !== 0,
    );

    const amounts = useMemo(() => {
        const values: Record<string, number> = {};

        for (const field of allFields) {
            values[field] = Number(data[field] || 0);
        }

        return values;
    }, [data]);

    const preview = useMemo(
        () => buildPostingPreview(amounts, mapping),
        [amounts, mapping],
    );

    const suggestedNet =
        (amountToCents(data.gross_sales) -
            amountToCents(data.discount_amount)) /
        100;
    const netIsOff =
        !netTouched &&
        suggestedNet > 0 &&
        amountToCents(data.net_sales) !== amountToCents(suggestedNet);

    const tendered = POS_TENDER_FIELDS.reduce(
        (total, field) => total + amountToCents(data[field.name]),
        0,
    );

    const latest = ["sales_format_3", "sales_format_4"].includes(data.calculation_version);
    const increments = [
        data.massage_increment_amount,
        data.laundry_increment_amount,
        data.key_card_increment_amount,
        data.amenities_increment_amount,
        data.avl_increment_amount,
        data.other_suppliers_increment_amount,
    ].reduce((sum, value) => sum + amountToCents(value), 0);
    // Accountant workbook sums I–AA, so column N is part of gross sales.
    const gross =
        incomeTotal +
        (latest
            ? increments + amountToCents(data.complimentary_room_amount)
            : tendered);
    const round = (value: number) => (latest ? value : Math.round(value));
    const roomTotal = POS_INCOME_FIELDS.slice(0, 5).reduce(
        (total, field) => total + amountToCents(data[field.name]),
        0,
    );
    const statutoryDiscount = ["senior_20", "pwd_20"].includes(
        data.discount_type,
    );
    const finalFormat = data.calculation_version === "sales_format_4";
    const exempt = finalFormat && ["non_vatable", "vat_exempt"].includes(data.vat_classification)
        ? round(gross / 1.12)
        : statutoryDiscount ? round(roomTotal / 1.12) : 0;
    const vatable = data.vat_classification === "vatable";
    // Accountant format keeps Non-VATable gross undivided; legacy files divide by 1.12.
    const taxable = latest
        ? finalFormat && !vatable
            ? 0
            : vatable
              ? gross / 1.12
              : gross
                : round(gross / 1.12);
    const vat = vatable ? round(taxable * 0.12) : 0;
    const discountRate =
        salesOptions.discountRates[data.discount_type] ??
        Number(data.discount_rate || 0);
    const discountBase =
        statutoryDiscount || (finalFormat && !vatable) ? exempt : taxable;
    const discount = round((discountBase * discountRate) / 100);
    const withholding = round(
        (taxable * Number(data.withholding_tax_rate || 0)) / 100,
    );
    const calculatedAmounts: Partial<Record<PosAmountField, number>> = {
        gross_sales: gross,
        taxable_amount: taxable,
        exempted_amount: exempt,
        vat_amount: vat,
        discount_amount: discount,
        withholding_tax_amount: withholding,
        net_sales: finalFormat && !vatable
            ? exempt - discount
            : latest && !vatable ? gross - discount : taxable + exempt - discount,
    };
    const amountValue = (field: PosAmountField) =>
        hasIncomeDetails && calculatedAmounts[field] !== undefined
            ? (calculatedAmounts[field]! / 100).toFixed(2)
            : data[field];
    const calculatedField = (field: PosAmountField) =>
        hasIncomeDetails && calculatedAmounts[field] !== undefined;

    const expected = hasIncomeDetails
        ? finalFormat && !vatable
            ? exempt - discount - withholding
            : latest && !vatable
              ? gross - discount - withholding
            : taxable + exempt + vat - discount - withholding
        : amountToCents(data.taxable_amount) +
          amountToCents(data.exempted_amount) +
          amountToCents(data.vat_amount) +
          amountToCents(data.local_tax_amount) +
          amountToCents(data.service_charge_amount);

    const variance = tendered - expected;

    const submit = (postNow: boolean) => {
        form.transform((payload) => {
            const next = { ...payload, post_now: postNow };
            if (hasIncomeDetails) {
                next.calculation_version = latest
                    ? data.calculation_version
                    : "sales_format_2";
                for (const [field, value] of Object.entries(
                    calculatedAmounts,
                )) {
                    next[field as PosAmountField] = (value! / 100).toFixed(2);
                }
            }

            for (const field of allFields) {
                next[field] = next[field] === "" ? "0" : next[field];
            }

            return next;
        });

        form[method === "put" ? "put" : "post"](action);
    };

    return (
        <AuthenticatedLayout
            title={heading.title}
            description={heading.description}
        >
            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-4">
                    <Link
                        href={backHref}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to POS entries
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
                                <CardTitle>Sales entry</CardTitle>
                                <CardDescription>
                                    Record a sales reference for the selected
                                    branch and date
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <Field
                                    label="Branch"
                                    htmlFor="branch_id"
                                    error={errors.branch_id}
                                >
                                    <Popover>
                                        <PopoverTrigger
                                            render={
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    role="combobox"
                                                    disabled={processing}
                                                    aria-invalid={
                                                        !!errors.branch_id
                                                    }
                                                    className="w-full justify-between font-normal"
                                                />
                                            }
                                        >
                                            {data.branch_id
                                                ? (() => {
                                                      const branch =
                                                          branches.find(
                                                              (branch) =>
                                                                  String(
                                                                      branch.id,
                                                                  ) ===
                                                                  String(
                                                                      data.branch_id,
                                                                  ),
                                                          );

                                                      return branch
                                                          ? `${branch.code} · ${branch.name}`
                                                          : "Choose a branch…";
                                                  })()
                                                : "Choose a branch…"}

                                            <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                                        </PopoverTrigger>

                                        <PopoverContent className="w-[var(--anchor-width)] p-0">
                                            <Command>
                                                <CommandInput placeholder="Search branch..." />

                                                <CommandList>
                                                    <CommandEmpty>
                                                        No branch found.
                                                    </CommandEmpty>

                                                    <CommandGroup>
                                                        {branches.map(
                                                            (branch) => {
                                                                const value =
                                                                    String(
                                                                        branch.id,
                                                                    );
                                                                const selected =
                                                                    String(
                                                                        data.branch_id,
                                                                    ) === value;

                                                                return (
                                                                    <CommandItem
                                                                        key={
                                                                            branch.id
                                                                        }
                                                                        value={`${branch.code} ${branch.name}`}
                                                                        onSelect={() =>
                                                                            setData(
                                                                                "branch_id",
                                                                                value,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Check
                                                                            className={
                                                                                selected
                                                                                    ? "mr-2 size-4 opacity-100"
                                                                                    : "mr-2 size-4 opacity-0"
                                                                            }
                                                                        />
                                                                        {
                                                                            branch.code
                                                                        }{" "}
                                                                        ·{" "}
                                                                        {
                                                                            branch.name
                                                                        }
                                                                    </CommandItem>
                                                                );
                                                            },
                                                        )}
                                                    </CommandGroup>
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                </Field>

                                <Field
                                    label="Business date"
                                    htmlFor="business_date"
                                    error={errors.business_date}
                                >
                                    <Input
                                        id="business_date"
                                        type="date"
                                        value={data.business_date}
                                        onChange={(event) =>
                                            setData(
                                                "business_date",
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.business_date}
                                        disabled={processing}
                                    />
                                </Field>

                                <Field
                                    label="POS reference"
                                    htmlFor="pos_reference"
                                    error={errors.pos_reference}
                                    hint="Unique per branch and date"
                                >
                                    <Input
                                        id="pos_reference"
                                        value={data.pos_reference}
                                        placeholder="Z-20260101"
                                        onChange={(event) =>
                                            setData(
                                                "pos_reference",
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!errors.pos_reference}
                                        disabled={processing}
                                    />
                                </Field>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Guest and sales details</CardTitle>
                                <CardDescription>
                                    Selections left blank remain unspecified.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {detailFields.map((field) => (
                                    <Field
                                        key={field.name}
                                        label={field.label}
                                        htmlFor={field.name}
                                        error={errors[field.name]}
                                    >
                                        <Input
                                            id={field.name}
                                            type={field.type}
                                            value={data[field.name]}
                                            disabled={processing}
                                            onChange={(event) =>
                                                setData(
                                                    field.name,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                ))}
                                {(
                                    [
                                        {
                                            name: "business_source",
                                            label: "Business source",
                                            options:
                                                salesOptions.businessSources,
                                        },
                                        {
                                            name: "vat_classification",
                                            label: "VAT classification",
                                            options:
                                                salesOptions.vatClassifications,
                                        },
                                        {
                                            name: "discount_type",
                                            label: "Discount",
                                            options: salesOptions.discountTypes,
                                        },
                                        {
                                            name: "withholding_tax_rate",
                                            label: "Withholding tax",
                                            options: Object.fromEntries(
                                                salesOptions.withholdingTaxRates.map(
                                                    (rate) => [
                                                        String(rate),
                                                        rate === 0
                                                            ? "None"
                                                            : `${rate}%`,
                                                    ],
                                                ),
                                            ),
                                        },
                                    ] as const
                                ).map((field) => (
                                    <Field
                                        key={field.name}
                                        label={field.label}
                                        htmlFor={field.name}
                                        error={errors[field.name]}
                                    >
                                        <select
                                            id={field.name}
                                            value={data[field.name]}
                                            disabled={processing}
                                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                            onChange={(event) => {
                                                const value = event.target.value;
                                                setData(field.name, value);
                                                if (
                                                    field.name ===
                                                        "discount_type" &&
                                                    !editableDiscountTypes.includes(
                                                        value,
                                                    )
                                                ) {
                                                    setData("discount_rate", "");
                                                }
                                            }}
                                        >
                                            <option value="">
                                                Unspecified
                                            </option>
                                            {Object.entries(field.options).map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </Field>
                                ))}
                                {editableDiscountTypes.includes(
                                    data.discount_type,
                                ) && (
                                    <Field
                                        label="Discount percentage"
                                        htmlFor="discount_rate"
                                        error={errors.discount_rate}
                                    >
                                        <Input
                                            id="discount_rate"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            required
                                            value={data.discount_rate}
                                            disabled={processing}
                                            aria-invalid={
                                                !!errors.discount_rate
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    "discount_rate",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Income breakdown</CardTitle>
                                <CardDescription>
                                    Enter the amounts from the sales invoice.
                                    Total: {formatCents(incomeTotal, currency)}.
                                    Save to view the calculated sales breakdown
                                    and journal entry.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {POS_INCOME_FIELDS.map((field) => (
                                    <Field
                                        key={field.name}
                                        label={
                                            latest &&
                                            [
                                                "spa_amount",
                                                "key_card_amount",
                                                "amenities_amount",
                                                "avl_amount",
                                                "other_income_amount",
                                            ].includes(field.name)
                                                ? `${field.label} (actual cost)`
                                                : field.label
                                        }
                                        htmlFor={field.name}
                                        error={errors[field.name]}
                                    >
                                        <Input
                                            id={field.name}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={data[field.name]}
                                            disabled={processing}
                                            onChange={(event) =>
                                                setData(
                                                    field.name,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle>Sales</CardTitle>
                                    <CardDescription>
                                        {hasIncomeDetails
                                            ? "Calculated from the workbook rules"
                                            : "Before discounts and taxes"}
                                    </CardDescription>
                                </div>
                                {netIsOff && !hasIncomeDetails && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                "net_sales",
                                                suggestedNet.toFixed(2),
                                            )
                                        }
                                        className="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline"
                                    >
                                        <Wand2 className="size-3.5" />
                                        Use{" "}
                                        {formatCents(
                                            suggestedNet * 100,
                                            currency,
                                        )}
                                    </button>
                                )}
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {POS_SALES_FIELDS.map((field) => (
                                    <Field
                                        key={field.name}
                                        label={field.label}
                                        htmlFor={field.name}
                                        error={errors[field.name]}
                                    >
                                        <Input
                                            id={field.name}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="text-right tabular-nums"
                                            value={amountValue(field.name)}
                                            disabled={
                                                processing ||
                                                calculatedField(field.name)
                                            }
                                            onChange={(event) => {
                                                setData(
                                                    field.name,
                                                    event.target.value,
                                                );

                                                if (
                                                    field.name === "net_sales"
                                                ) {
                                                    setNetTouched(true);
                                                }
                                            }}
                                        />
                                    </Field>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Taxes and charges</CardTitle>
                                <CardDescription>
                                    Held back as liabilities when the entry is
                                    posted
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {POS_TAX_FIELDS.map((field) => (
                                    <Field
                                        key={field.name}
                                        label={field.label}
                                        htmlFor={field.name}
                                        error={errors[field.name]}
                                    >
                                        <Input
                                            id={field.name}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="text-right tabular-nums"
                                            value={amountValue(field.name)}
                                            disabled={
                                                processing ||
                                                calculatedField(field.name)
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    field.name,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle>Tendered</CardTitle>
                                    <CardDescription>
                                        What each payment method actually took
                                        in
                                    </CardDescription>
                                </div>
                                <span className="text-muted-foreground text-xs">
                                    {formatCents(tendered, currency)} total
                                </span>
                            </CardHeader>
                            <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {POS_TENDER_FIELDS.map((field) => {
                                    return (
                                        <Field
                                            key={field.name}
                                            label={field.label}
                                            htmlFor={field.name}
                                            error={errors[field.name]}
                                            // hint={
                                            //     row?.code
                                            //         ? `Dr ${row.code} · ${row.name ?? ""}`
                                            //         : `No account mapped for ${field.label}`
                                            // }
                                        >
                                            <Input
                                                id={field.name}
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                className="text-right tabular-nums"
                                                value={data[field.name]}
                                                disabled={processing}
                                                onChange={(event) =>
                                                    setData(
                                                        field.name,
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    );
                                })}
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
                                    placeholder="Cash short by 40 because of an unposted refund…"
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
                                disabled={
                                    processing ||
                                    branches.length === 0 ||
                                    hasIncomeDetails
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
                    {hasIncomeDetails ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Sales draft</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p>
                                    Income total:{" "}
                                    {formatCents(incomeTotal, currency)}
                                </p>
                                <p>
                                    Payments: {formatCents(tendered, currency)}
                                </p>
                                <p>
                                    Amount due:{" "}
                                    {formatCents(expected, currency)}
                                </p>
                                <p>
                                    Difference:{" "}
                                    {formatCentsSigned(variance, currency)}
                                </p>
                                <p className="text-muted-foreground">
                                    Amounts follow the workbook’s written
                                    formulas. Save to review the full
                                    calculation and journal preview.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Tie-out</CardTitle>
                                    <CardDescription>
                                        Tendered against net sales plus taxes
                                        and service charge
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <Row
                                        label="Tendered"
                                        value={formatCents(tendered, currency)}
                                    />
                                    <Row
                                        label="Expected"
                                        value={formatCents(expected, currency)}
                                    />
                                    <div
                                        className={
                                            variance === 0
                                                ? "bg-primary/10 flex items-center justify-between rounded-md px-2.5 py-1.5 font-medium"
                                                : "bg-destructive/10 text-destructive flex items-center justify-between rounded-md px-2.5 py-1.5 font-medium"
                                        }
                                    >
                                        <span>
                                            {variance === 0
                                                ? "Tied out"
                                                : "Variance"}
                                        </span>
                                        <span className="tabular-nums">
                                            {variance === 0
                                                ? formatCents(0, currency)
                                                : formatCentsSigned(
                                                      variance,
                                                      currency,
                                                  )}
                                        </span>
                                    </div>
                                    {variance !== 0 && (
                                        <p className="text-muted-foreground text-xs">
                                            The difference is posted to the
                                            over/short account so the journal
                                            entry still balances.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Journal entry preview</CardTitle>
                                    <CardDescription>
                                        Written when you post this summary
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-1.5 text-sm">
                                    {preview.empty ? (
                                        <p className="text-muted-foreground text-xs">
                                            Enter amounts to see the posting.
                                        </p>
                                    ) : (
                                        preview.lines.map((line, index) => (
                                            <div
                                                key={`${line.label}-${index}`}
                                                className="flex items-baseline justify-between gap-2"
                                            >
                                                <span className="min-w-0 truncate">
                                                    <span className="text-muted-foreground font-mono text-[11px]">
                                                        {line.code ?? "??"}
                                                    </span>{" "}
                                                    <span className="text-xs">
                                                        {line.label}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 tabular-nums">
                                                    {line.debit
                                                        ? formatCents(
                                                              line.debit,
                                                              currency,
                                                          )
                                                        : formatCents(
                                                              line.credit,
                                                              currency,
                                                          )}
                                                </span>
                                            </div>
                                        ))
                                    )}
                                    {preview.missingCodes.length > 0 && (
                                        <Alert
                                            variant="destructive"
                                            className="mt-3"
                                        >
                                            <AlertTitle>
                                                No account mapped
                                            </AlertTitle>
                                            <AlertDescription>
                                                Add these accounts to the chart
                                                of accounts:{" "}
                                                {preview.missingCodes.join(
                                                    ", ",
                                                )}
                                                .
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    )}
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
