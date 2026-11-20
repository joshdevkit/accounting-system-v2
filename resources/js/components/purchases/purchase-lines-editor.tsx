import { useState } from "react";
import { Check, ChevronsUpDown, Plus, TrashOff } from "lucide-react";

import { Input } from "@/components/ui/input";
import { amountToCents, formatCents } from "@/lib/format";
import { AccountCombobox, AccountGroup } from "../journal/account-combobox";
import { Popover, PopoverContent, PopoverTrigger } from "../ui/popover";
import { Button } from "../ui/button";
import { Command, CommandGroup, CommandItem, CommandList } from "../ui/command";
import { UomPicker } from "../ui/UomPicker";

export type PurchaseLineInput = {
    account_id: number | "";
    particulars: string;
    quantity: string;
    uom: string;
    unit_price: string;
    amount: string;
    tax_type: "vatable" | "non_vat" | "vat_exempt";
    withholding_tax_rate: string;
    description: string;
};

export function emptyPurchaseLine(): PurchaseLineInput {
    return {
        account_id: "",
        particulars: "",
        quantity: "1",
        uom: "",
        unit_price: "",
        amount: "",
        tax_type: "non_vat",
        withholding_tax_rate: "0",
        description: "",
    };
}

export function purchaseLinesTotals(
    lines: PurchaseLineInput[] = [],
    vatRate: number,
) {
    let taxable = 0;
    let vat = 0;
    let withholding = 0;

    for (const line of lines) {
        const amount = amountToCents(line.amount);
        const rate = Number(line.withholding_tax_rate || 0);

        taxable += amount;
        vat += line.tax_type === "vatable" ? Math.round(amount * vatRate) : 0;
        withholding += rate > 0 ? Math.round(amount * (rate / 100)) : 0;
    }

    return {
        taxable,
        vat,
        withholding,
        gross: taxable + vat,
    };
}

export function PurchaseLinesEditor({
    lines,
    accounts,
    errors,
    disabled,
    currency,
    vatRate,
    withholdingRates,
    onChange,
}: {
    lines: PurchaseLineInput[];
    accounts: AccountGroup[];
    errors: Record<string, string | undefined>;
    disabled?: boolean;
    currency: string;
    vatRate: number;
    withholdingRates: { value: string; label: string }[];
    onChange: (lines: PurchaseLineInput[]) => void;
}) {
    const [removingIndex, setRemovingIndex] = useState<number | null>(null);

    const totals = purchaseLinesTotals(lines, vatRate);

    const update = (index: number, patch: Partial<PurchaseLineInput>) => {
        onChange(
            lines.map((line, position) => {
                if (position !== index) {
                    return line;
                }

                const next = {
                    ...line,
                    ...patch,
                };

                // Auto-calc amount from qty × unit price, unless the user is
                // editing amount directly.
                if (
                    patch.quantity !== undefined ||
                    patch.unit_price !== undefined
                ) {
                    const qty = Number(next.quantity || 0);
                    const price = Number(next.unit_price || 0);

                    next.amount = (qty * price).toFixed(2);
                }

                return next;
            }),
        );
    };

    const removeLine = (index: number) => {
        if (disabled || lines.length === 1 || removingIndex !== null) {
            return;
        }

        setRemovingIndex(index);

        window.setTimeout(() => {
            onChange(lines.filter((_, position) => position !== index));

            setRemovingIndex(null);
        }, 200);
    };

    const withholdingLabel = (value: string) =>
        withholdingRates.find((rate) => rate.value === value)?.label ?? value;

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full min-w-300 text-sm">
                    <thead>
                        <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs">
                            <th className="w-8 px-3 py-2 font-medium">#</th>

                            <th className="px-3 py-2 font-medium">
                                Particulars
                            </th>

                            <th className="w-20 px-3 py-2 text-right font-medium">
                                Qty
                            </th>

                            <th className="w-28 px-3 py-2 font-medium">UOM</th>

                            <th className="w-28 px-3 py-2 text-right font-medium">
                                Unit price
                            </th>

                            <th className="w-28 px-3 py-2 text-right font-medium">
                                Amount
                            </th>

                            <th className="w-28 px-3 py-2 font-medium">Tax</th>

                            <th className="w-32 px-3 py-2 font-medium">
                                Withholding
                            </th>

                            <th className="px-3 py-2 font-medium">Account</th>

                            <th className="w-10 px-3 py-2" />
                        </tr>
                    </thead>

                    <tbody>
                        {lines.map((line, index) => {
                            const isRemoving = removingIndex === index;

                            return (
                                <tr
                                    key={index}
                                    className={[
                                        "border-b last:border-0",
                                        "transition-opacity duration-200 ease-out",
                                        isRemoving
                                            ? "opacity-0"
                                            : "opacity-100",
                                    ].join(" ")}
                                >
                                    <td className="text-muted-foreground px-3 py-2 text-xs tabular-nums">
                                        {index + 1}
                                    </td>

                                    <td className="px-3 py-2">
                                        <Input
                                            aria-label={`Line ${index + 1} particulars`}
                                            value={line.particulars}
                                            placeholder="Item description"
                                            disabled={disabled}
                                            onChange={(event) =>
                                                update(index, {
                                                    particulars:
                                                        event.target.value,
                                                })
                                            }
                                        />

                                        {errors[
                                            `lines.${index}.particulars`
                                        ] && (
                                            <p className="text-destructive mt-1 text-xs">
                                                {
                                                    errors[
                                                        `lines.${index}.particulars`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </td>

                                    <td className="px-3 py-2">
                                        <Input
                                            aria-label={`Line ${index + 1} quantity`}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="text-right tabular-nums"
                                            value={line.quantity}
                                            disabled={disabled}
                                            onChange={(event) =>
                                                update(index, {
                                                    quantity:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </td>

                                    <td className="px-3 py-2">
                                        <UomPicker
                                            label={`Line ${index + 1} unit of measure`}
                                            value={line.uom}
                                            disabled={disabled}
                                            onChange={(uom) =>
                                                update(index, { uom })
                                            }
                                        />
                                    </td>

                                    <td className="px-3 py-2">
                                        <Input
                                            aria-label={`Line ${index + 1} unit price`}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="text-right tabular-nums"
                                            value={line.unit_price}
                                            disabled={disabled}
                                            onChange={(event) =>
                                                update(index, {
                                                    unit_price:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </td>

                                    <td className="px-3 py-2">
                                        <Input
                                            aria-label={`Line ${index + 1} amount`}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="text-right tabular-nums"
                                            value={line.amount}
                                            disabled={disabled}
                                            onChange={(event) =>
                                                update(index, {
                                                    amount: event.target.value,
                                                })
                                            }
                                        />

                                        {errors[`lines.${index}.amount`] && (
                                            <p className="text-destructive mt-1 text-xs">
                                                {
                                                    errors[
                                                        `lines.${index}.amount`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </td>

                                    <td className="px-3 py-2">
                                        <Popover>
                                            <PopoverTrigger
                                                render={
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        role="combobox"
                                                        disabled={disabled}
                                                        className="h-9 w-full min-w-32 justify-between"
                                                    />
                                                }
                                            >
                                                {line.tax_type === "non_vat"
                                                    ? "Non-VAT"
                                                    : line.tax_type ===
                                                        "vatable"
                                                      ? "VATable"
                                                      : "VAT-exempt"}

                                                <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                                            </PopoverTrigger>

                                            <PopoverContent className="w-40 p-0">
                                                <Command>
                                                    <CommandList>
                                                        <CommandGroup>
                                                            <CommandItem
                                                                value="non_vat"
                                                                onSelect={() =>
                                                                    update(
                                                                        index,
                                                                        {
                                                                            tax_type:
                                                                                "non_vat",
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Check
                                                                    className={
                                                                        line.tax_type ===
                                                                        "non_vat"
                                                                            ? "mr-2 size-4 opacity-100"
                                                                            : "mr-2 size-4 opacity-0"
                                                                    }
                                                                />
                                                                Non-VAT
                                                            </CommandItem>

                                                            <CommandItem
                                                                value="vatable"
                                                                onSelect={() =>
                                                                    update(
                                                                        index,
                                                                        {
                                                                            tax_type:
                                                                                "vatable",
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Check
                                                                    className={
                                                                        line.tax_type ===
                                                                        "vatable"
                                                                            ? "mr-2 size-4 opacity-100"
                                                                            : "mr-2 size-4 opacity-0"
                                                                    }
                                                                />
                                                                VATable
                                                            </CommandItem>

                                                            <CommandItem
                                                                value="vat_exempt"
                                                                onSelect={() =>
                                                                    update(
                                                                        index,
                                                                        {
                                                                            tax_type:
                                                                                "vat_exempt",
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Check
                                                                    className={
                                                                        line.tax_type ===
                                                                        "vat_exempt"
                                                                            ? "mr-2 size-4 opacity-100"
                                                                            : "mr-2 size-4 opacity-0"
                                                                    }
                                                                />
                                                                VAT-exempt
                                                            </CommandItem>
                                                        </CommandGroup>
                                                    </CommandList>
                                                </Command>
                                            </PopoverContent>
                                        </Popover>
                                    </td>

                                    <td className="px-3 py-2">
                                        <Popover>
                                            <PopoverTrigger
                                                render={
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        role="combobox"
                                                        disabled={disabled}
                                                        className="h-9 w-full min-w-28 justify-between"
                                                    />
                                                }
                                            >
                                                {withholdingLabel(
                                                    line.withholding_tax_rate,
                                                )}

                                                <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                                            </PopoverTrigger>

                                            <PopoverContent className="w-36 p-0">
                                                <Command>
                                                    <CommandList>
                                                        <CommandGroup>
                                                            {withholdingRates.map(
                                                                (rate) => (
                                                                    <CommandItem
                                                                        key={
                                                                            rate.value
                                                                        }
                                                                        value={
                                                                            rate.label
                                                                        }
                                                                        onSelect={() =>
                                                                            update(
                                                                                index,
                                                                                {
                                                                                    withholding_tax_rate:
                                                                                        rate.value,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <Check
                                                                            className={
                                                                                line.withholding_tax_rate ===
                                                                                rate.value
                                                                                    ? "mr-2 size-4 opacity-100"
                                                                                    : "mr-2 size-4 opacity-0"
                                                                            }
                                                                        />
                                                                        {
                                                                            rate.label
                                                                        }
                                                                    </CommandItem>
                                                                ),
                                                            )}
                                                        </CommandGroup>
                                                    </CommandList>
                                                </Command>
                                            </PopoverContent>
                                        </Popover>

                                        {errors[
                                            `lines.${index}.withholding_tax_rate`
                                        ] && (
                                            <p className="text-destructive mt-1 text-xs">
                                                {
                                                    errors[
                                                        `lines.${index}.withholding_tax_rate`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </td>

                                    <td className="px-3 py-2">
                                        <AccountCombobox
                                            aria-label={`Line ${index + 1} account`}
                                            value={line.account_id}
                                            disabled={disabled}
                                            groups={accounts}
                                            onChange={(value) =>
                                                update(index, {
                                                    account_id: value,
                                                })
                                            }
                                            className="min-w-48"
                                        />

                                        {errors[
                                            `lines.${index}.account_id`
                                        ] && (
                                            <p className="text-destructive mt-1 text-xs">
                                                {
                                                    errors[
                                                        `lines.${index}.account_id`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </td>

                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            aria-label={`Remove line ${index + 1}`}
                                            disabled={
                                                disabled ||
                                                lines.length === 1 ||
                                                removingIndex !== null
                                            }
                                            onClick={() => removeLine(index)}
                                            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive flex size-7 items-center justify-center rounded-md transition-colors disabled:opacity-40"
                                        >
                                            <TrashOff className="size-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>

                    <tfoot>
                        <tr className="bg-muted/30 font-medium">
                            <td />

                            <td
                                className="text-muted-foreground px-3 py-2 text-xs"
                                colSpan={6}
                            >
                                <button
                                    type="button"
                                    disabled={
                                        disabled || removingIndex !== null
                                    }
                                    onClick={() =>
                                        onChange([
                                            ...lines,
                                            emptyPurchaseLine(),
                                        ])
                                    }
                                    className="text-primary inline-flex items-center gap-1 hover:underline disabled:opacity-50"
                                >
                                    <Plus className="size-3.5" />
                                    Add line
                                </button>
                            </td>

                            <td
                                colSpan={2}
                                className="px-3 py-2 text-right text-xs"
                            >
                                Taxable {formatCents(totals.taxable, currency)}{" "}
                                · VAT {formatCents(totals.vat, currency)}
                                {totals.withholding > 0 && (
                                    <>
                                        {" "}
                                        · WHT{" "}
                                        {formatCents(
                                            totals.withholding,
                                            currency,
                                        )}
                                    </>
                                )}
                            </td>

                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}
