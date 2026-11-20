import { formatCents } from "@/lib/format";
import { Card, CardContent, CardHeader, CardTitle } from "../ui/card";

export function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-2 border-b pb-1.5 last:border-0 last:pb-0">
            <span className="text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}

export function JournalTable({
    title,
    lines,
    currency,
}: {
    title: string;
    lines: { account_code: string | null; account_name: string | null; debit_cents: number; credit_cents: number }[];
    currency: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs">
                            <th className="px-4 py-2 font-medium">Account</th>
                            <th className="px-4 py-2 text-right font-medium">Debit</th>
                            <th className="px-4 py-2 text-right font-medium">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        {lines.map((line, index) => (
                            <tr key={index} className="border-b last:border-0">
                                <td className="px-4 py-2">
                                    <span className="text-muted-foreground font-mono text-xs">
                                        {line.account_code}
                                    </span>{' '}
                                    {line.account_name}
                                </td>
                                <td className="px-4 py-2 text-right tabular-nums">
                                    {line.debit_cents ? formatCents(line.debit_cents, currency) : '—'}
                                </td>
                                <td className="px-4 py-2 text-right tabular-nums">
                                    {line.credit_cents ? formatCents(line.credit_cents, currency) : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}