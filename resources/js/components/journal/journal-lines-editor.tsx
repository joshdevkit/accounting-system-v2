import { useState } from "react";
import { Plus, Trash2 } from "lucide-react";

import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { amountToCents, formatCents } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { AccountOption, JournalLineInput } from "@/types/models";
import { AccountCombobox } from "./account-combobox";

const CATEGORY_ORDER = [
    "asset",
    "liability",
    "equity",
    "revenue",
    "expense",
] as const;

export function linesTotals(lines: JournalLineInput[]) {
    let debits = 0;
    let credits = 0;

    for (const line of lines) {
        debits += amountToCents(line.debit);
        credits += amountToCents(line.credit);
    }

    return {
        debits,
        credits,
        difference: debits - credits,
    };
}

export function emptyLine(): JournalLineInput {
    return {
        account_id: "",
        debit: "",
        credit: "",
        description: "",
    };
}

export function JournalLinesEditor({
    lines,
    accounts,
    errors,
    disabled,
    currency,
    onChange,
}: {
    lines: JournalLineInput[];
    accounts: AccountOption[];
    errors: Record<string, string | undefined>;
    disabled?: boolean;
    currency: string;
    onChange: (lines: JournalLineInput[]) => void;
}) {
    const [removingIndex, setRemovingIndex] = useState<number | null>(
        null,
    );

    const totals = linesTotals(lines);

    const grouped = CATEGORY_ORDER.map((category) => ({
        category,
        options: accounts.filter(
            (account) => account.category === category,
        ),
    })).filter((group) => group.options.length > 0);

    const update = (
        index: number,
        patch: Partial<JournalLineInput>,
    ) => {
        onChange(
            lines.map((line, position) =>
                position === index
                    ? { ...line, ...patch }
                    : line,
            ),
        );
    };

    const removeLine = (index: number) => {
        if (
            disabled ||
            lines.length === 1 ||
            removingIndex !== null
        ) {
            return;
        }

        setRemovingIndex(index);

        window.setTimeout(() => {
            onChange(
                lines.filter(
                    (_, position) => position !== index,
                ),
            );

            setRemovingIndex(null);
        }, 200);
    };

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full min-w-175 text-sm">
                    <thead>
                        <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs">
                            <th className="w-10 px-3 py-2 font-medium">
                                #
                            </th>

                            <th className="px-3 py-2 font-medium">
                                Account
                            </th>

                            <th className="w-36 px-3 py-2 text-right font-medium">
                                Debit
                            </th>

                            <th className="w-36 px-3 py-2 text-right font-medium">
                                Credit
                            </th>

                            <th className="px-3 py-2 font-medium">
                                Memo
                            </th>

                            <th className="w-10 px-3 py-2" />
                        </tr>
                    </thead>

                    <tbody>
                        {lines.map((line, index) => {
                            const isRemoving =
                                removingIndex === index;

                            return (
                                <tr
                                    key={index}
                                    className={cn(
                                        "border-b last:border-0",
                                        "transition-opacity duration-200 ease-out",
                                        isRemoving
                                            ? "opacity-0"
                                            : "opacity-100",
                                    )}
                                >
                                    <td className="text-muted-foreground px-3 py-2 text-xs tabular-nums">
                                        {index + 1}
                                    </td>

                                    <td className="px-3 py-2">
                                        <AccountCombobox
                                            aria-label={`Line ${index + 1} account`}
                                            value={
                                                line.account_id ===
                                                ""
                                                    ? ""
                                                    : Number(
                                                          line.account_id,
                                                      )
                                            }
                                            disabled={disabled}
                                            groups={grouped}
                                            onChange={(value) =>
                                                update(index, {
                                                    account_id:
                                                        value,
                                                })
                                            }
                                            className="min-w-52"
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
                                        <Input
                                            aria-label={`Line ${index + 1} debit`}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            inputMode="decimal"
                                            className="text-right tabular-nums"
                                            value={line.debit}
                                            disabled={disabled}
                                            onChange={(event) =>
                                                update(index, {
                                                    debit: event
                                                        .target
                                                        .value,
                                                    credit: event
                                                        .target
                                                        .value
                                                        ? ""
                                                        : line.credit,
                                                })
                                            }
                                        />
                                    </td>

                                    <td className="px-3 py-2">
                                        <Input
                                            aria-label={`Line ${index + 1} credit`}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            inputMode="decimal"
                                            className="text-right tabular-nums"
                                            value={line.credit}
                                            disabled={disabled}
                                            onChange={(event) =>
                                                update(index, {
                                                    credit: event
                                                        .target
                                                        .value,
                                                    debit: event
                                                        .target
                                                        .value
                                                        ? ""
                                                        : line.debit,
                                                })
                                            }
                                        />
                                    </td>

                                    <td className="px-3 py-2">
                                        <Input
                                            aria-label={`Line ${index + 1} memo`}
                                            value={
                                                line.description
                                            }
                                            placeholder="Optional"
                                            disabled={disabled}
                                            onChange={(event) =>
                                                update(index, {
                                                    description:
                                                        event.target
                                                            .value,
                                                })
                                            }
                                        />
                                    </td>

                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            aria-label={`Remove line ${index + 1}`}
                                            disabled={
                                                disabled ||
                                                lines.length === 1 ||
                                                removingIndex !==
                                                    null
                                            }
                                            onClick={() =>
                                                removeLine(index)
                                            }
                                            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive flex size-7 items-center justify-center rounded-md transition-colors disabled:opacity-40"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>

                    <tfoot>
                        <tr className="bg-muted/30 font-medium">
                            <td />

                            <td className="text-muted-foreground px-3 py-2 text-xs">
                                <button
                                    type="button"
                                    disabled={
                                        disabled ||
                                        removingIndex !== null
                                    }
                                    onClick={() =>
                                        onChange([
                                            ...lines,
                                            emptyLine(),
                                        ])
                                    }
                                    className="text-primary inline-flex items-center gap-1 hover:underline disabled:opacity-50"
                                >
                                    <Plus className="size-3.5" />
                                    Add line
                                </button>
                            </td>

                            <td className="px-3 py-2 text-right tabular-nums">
                                {formatCents(
                                    totals.debits,
                                    currency,
                                )}
                            </td>

                            <td className="px-3 py-2 text-right tabular-nums">
                                {formatCents(
                                    totals.credits,
                                    currency,
                                )}
                            </td>

                            <td
                                colSpan={2}
                                className="px-3 py-2"
                            >
                                <span
                                    className={cn(
                                        "block text-right text-xs",
                                        totals.difference === 0
                                            ? "text-primary"
                                            : "text-destructive",
                                    )}
                                >
                                    {totals.difference === 0
                                        ? "Balanced"
                                        : `Out by ${formatCents(
                                              Math.abs(
                                                  totals.difference,
                                              ),
                                              currency,
                                          )}`}
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {(errors.lines ||
                errors["lines.0.debit"]) && (
                <p className="text-destructive text-xs">
                    {errors.lines ??
                        errors["lines.0.debit"]}
                </p>
            )}
        </div>
    );
}