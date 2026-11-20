import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { formatCents, formatCentsSigned } from "@/lib/format";

export type PosWorkbookPreview = {
    sales: {
        calculation_version?: string;
        income_total: number;
        room_total: number;
        payments_total: number;
        gross_sales: number;
        taxable_amount: number;
        exempted_amount: number;
        vat_amount: number;
        discount_amount: number;
        withholding_tax_amount: number;
        amount_due: number;
        discount_rate: number | null;
        discount_base: number;
        withholding_base: number;
        missing_inputs: string[];
    };
    rows: {
        label: string;
        side: "debit" | "credit";
        amount: number | null;
        code: string | null;
        account_resolved?: boolean;
        basis: string;
    }[];
    debits: number;
    credits: number;
    difference: number;
    complete: boolean;
};

const columns = [
    ["gross_sales", "VAT-inclusive sales"],
    ["taxable_amount", "VATable sales"],
    ["exempted_amount", "VAT-exempt sales"],
    ["vat_amount", "12% VAT"],
    ["discount_amount", "Discount"],
    ["withholding_tax_amount", "Withholding"],
    ["amount_due", "Amount due"],
] as const;

export function PosWorkbookCalculation({
    preview,
    currency,
}: {
    preview: PosWorkbookPreview;
    currency: string;
}) {
    const pendingDiscount = preview.sales.missing_inputs.length > 0;
    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>Sales invoice calculation</CardTitle>
                    <CardDescription>
                        Calculated using the Sales Format workbook instructions.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr>
                                    {columns.map(([field, label]) => (
                                        <th
                                            key={field}
                                            className="border-b px-3 py-2 text-right font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    {columns.map(([field]) => (
                                        <td
                                            key={field}
                                            className="px-3 py-3 text-right whitespace-nowrap tabular-nums"
                                        >
                                            {pendingDiscount &&
                                            (field === "discount_amount" ||
                                                field === "amount_due")
                                                ? "Rate required"
                                                : formatCents(
                                                      preview.sales[field],
                                                      currency,
                                                  )}
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <details className="text-muted-foreground text-sm">
                        <summary className="cursor-pointer font-medium">
                            Calculation details
                        </summary>
                        <div className="mt-2 space-y-1">
                            <p>
                                Gross sales: I–M + N + O–AA. Income{" "}
                                {formatCents(
                                    preview.sales.income_total,
                                    currency,
                                )}
                                .
                                {["sales_format_3", "sales_format_4"].includes(preview.sales.calculation_version ?? "")
                                    ? ` Plus complementary rooms (N) ${formatCents(preview.sales.gross_sales - preview.sales.income_total, currency)}; payments excluded.`
                                    : ` Plus payments ${formatCents(preview.sales.payments_total, currency)}; complementary rooms excluded.`}
                            </p>
                            {["sales_format_3", "sales_format_4"].includes(preview.sales.calculation_version ?? "") && (
                                <p>
                                    Workbook totals retain unrounded formula
                                    precision (including the variance). Saved
                                    journal lines use two decimals, <br/> so their
                                    difference can vary by one centavo. JE
                                    output VAT excludes gift vouchers; the sales
                                    report VAT follows its separate workbook
                                    formula.
                                </p>
                            )}
                            <p>
                                VATable sales: gross sales ÷ 1.12 when the
                                invoice is VATable. Maxi (Final) puts gross ÷
                                1.12 in VAT Exempt Sales for Non-VATable sales.
                                Senior/PWD VAT-exempt sales use room charges
                                I–M ÷ 1.12.
                            </p>
                            <p>
                                VAT: VATable sales × 12% only when VATable is
                                selected.
                            </p>
                            <p>
                                Discount:{" "}
                                {preview.sales.discount_rate === null
                                    ? "percentage required"
                                    : preview.sales.discount_rate + "%"}{" "}
                                ×{" "}
                                {formatCents(
                                    preview.sales.discount_base,
                                    currency,
                                )}
                                . Senior/PWD use exempt room charges; other
                                discounts use VATable sales.
                            </p>
                            <p>
                                Withholding: selected percentage × VATable sales
                                (
                                {formatCents(
                                    preview.sales.withholding_base,
                                    currency,
                                )}
                                ).
                            </p>
                            <p>
                                Amount due: VATable + VAT-exempt + VAT −
                                discount − withholding. Unselected
                                discount/withholding rates produce zero; <br /> blank
                                VAT classification does not trigger VAT.
                            </p>
                        </div>
                    </details>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>
                        {["sales_format_3", "sales_format_4"].includes(preview.sales.calculation_version ?? "")
                            ? "Saved draft journal — workbook calculation"
                            : "Journal entry preview"}
                    </CardTitle>
                    <CardDescription>
                        Amounts replace the workbook’s xx placeholders. This
                        preview has not been posted to the ledger.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="py-2">Account title</th>
                                    <th className="px-3 py-2 text-right">
                                        Debit
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Credit
                                    </th>
                                    <th className="px-3 py-2">Basis</th>
                                </tr>
                            </thead>
                            <tbody>
                                {preview.rows.map((row) => (
                                    <tr key={row.label} className="border-b">
                                        <td className="py-2">
                                            {row.label}
                                            {row.code && (
                                                <span className="text-muted-foreground ml-2 font-mono text-xs">
                                                    {row.code}
                                                </span>
                                            )}
                                            {row.amount !== 0 &&
                                                (!row.code ||
                                                    row.account_resolved ===
                                                        false) && (
                                                    <span className="ml-2 text-xs text-amber-700">
                                                        Account mapping required
                                                    </span>
                                                )}
                                        </td>
                                        {(["debit", "credit"] as const).map(
                                            (side) => (
                                                <td
                                                    key={side}
                                                    className="px-3 py-2 text-right whitespace-nowrap tabular-nums"
                                                >
                                                    {row.side !== side
                                                        ? "—"
                                                        : row.amount === null
                                                          ? "Input required"
                                                          : formatCents(
                                                                row.amount,
                                                                currency,
                                                            )}
                                                </td>
                                            ),
                                        )}
                                        <td className="text-muted-foreground px-3 py-2 text-xs">
                                            {row.basis}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="font-semibold">
                                    <td className="py-3">
                                        {preview.complete
                                            ? "Totals"
                                            : "Known amounts subtotal"}
                                    </td>
                                    <td className="px-3 text-right tabular-nums">
                                        {formatCents(preview.debits, currency)}
                                    </td>
                                    <td className="px-3 text-right tabular-nums">
                                        {formatCents(preview.credits, currency)}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p className="text-sm">
                        Debit less credit:{" "}
                        {Math.round(Math.abs(preview.difference)) === 0
                            ? formatCents(0, currency)
                            : formatCentsSigned(preview.difference, currency)}
                        {!preview.complete && " (before missing inputs)"}. No
                        balancing adjustment has been added.
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
