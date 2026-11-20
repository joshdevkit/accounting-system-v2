import { Link, useForm } from "@inertiajs/react";
import { Check, ChevronsUpDown } from "lucide-react";
import { useState, type FormEvent } from "react";

import { JournalLinesEditor, emptyLine, linesTotals } from "@/components/journal/journal-lines-editor";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { formatCents } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { AccountOption, JournalLineInput } from "@/types/models";

export type LinkedIntercompanyJournal = {
    id: number;
    reference_no: string;
    status: string;
    lines: {
        account_code: string | null;
        account_name: string | null;
        debit_cents: number;
        credit_cents: number;
    }[];
};

export function LinkedIntercompanyEntry({
    source,
    sourceId,
    date,
    currency,
    entry,
    voucher,
    canMarkForVoucher,
    accounts,
    branches,
}: {
    source: "purchases" | "expenses";
    sourceId: number;
    date: string;
    currency: string;
    entry: LinkedIntercompanyJournal | null;
    voucher: { id: number; status: string } | null;
    canMarkForVoucher: boolean;
    accounts: AccountOption[];
    branches: { id: number; name: string; code: string }[];
}) {
    const form = useForm<{
        transaction_date: string;
        branch_id: number | "";
        description: string;
        lines: JournalLineInput[];
    }>({
        transaction_date: date,
        branch_id: "",
        description: "Intercompany clearing entry",
        lines: [emptyLine(), emptyLine()],
    });
    const voucherForm = useForm({});
    const voucherError = (voucherForm.errors as Record<string, string | undefined>).voucher;
    const [branchOpen, setBranchOpen] = useState(false);
    const selectedBranch = branches.find((branch) => branch.id === form.data.branch_id);
    const totals = linesTotals(form.data.lines);
    const canSubmit = form.data.branch_id !== "" && totals.debits > 0 && totals.debits === totals.credits;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/${source}/${sourceId}/intercompany-journal`);
    };

    return (
        <Card id="intercompany-entry">
            <CardHeader>
                <CardTitle>Intercompany Clearing Entry</CardTitle>
                <CardDescription>
                    Enter the other branch's journal entry for this {source === "purchases" ? "purchase" : "expense"} using debit and credit lines only. No itemized lines are needed.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {entry ? (
                    <div className="space-y-3">
                        <p className="text-sm">
                            <Link href={`/journal-entries/${entry.id}`} className="text-primary underline underline-offset-4">
                                {entry.reference_no}
                            </Link>{" "}
                            · {entry.status}
                        </p>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-medium">Account</th>
                                        <th className="px-4 py-2 text-right font-medium">Debit</th>
                                        <th className="px-4 py-2 text-right font-medium">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {entry.lines.map((line, index) => (
                                        <tr key={index} className="border-t">
                                            <td className="px-4 py-2">{line.account_code} · {line.account_name}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">{line.debit_cents ? formatCents(line.debit_cents, currency) : "—"}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">{line.credit_cents ? formatCents(line.credit_cents, currency) : "—"}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {entry.status === "posted" && !voucher && canMarkForVoucher && (
                            <Button type="button" disabled={voucherForm.processing} onClick={() => voucherForm.post(`/${source}/${sourceId}/intercompany-voucher`)}>
                                For Voucher
                            </Button>
                        )}
                        {voucherError && <p className="text-destructive text-sm">{voucherError}</p>}
                        {voucher?.status === "for_voucher" && (
                            <p className="text-sm">For Voucher · <Link href="/outgoing-payments" className="text-primary underline underline-offset-4">View in Outgoing Payments</Link></p>
                        )}
                        {voucher?.status === "paid" && <p className="text-sm">Paid through Outgoing Payments</p>}
                    </div>
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        <p className="text-muted-foreground text-sm">
                            For the Gusto to Maxi example: choose Maxi, then debit Due from Gusto and credit Maxi A/P Clearing. After posting, click For Voucher to send this entry to Outgoing Payments.
                        </p>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="space-y-1">
                                <label htmlFor={`${source}-intercompany-date`} className="text-sm font-medium">Date</label>
                                <Input id={`${source}-intercompany-date`} type="date" value={form.data.transaction_date} onChange={(event) => form.setData("transaction_date", event.target.value)} />
                                {form.errors.transaction_date && <p className="text-destructive text-xs">{form.errors.transaction_date}</p>}
                            </div>
                            <div className="space-y-1">
                                <label htmlFor={`${source}-intercompany-branch`} className="text-sm font-medium">Branch for this entry</label>
                                <Popover open={branchOpen} onOpenChange={setBranchOpen}>
                                    <PopoverTrigger render={
                                        <Button
                                            id={`${source}-intercompany-branch`}
                                            type="button"
                                            variant="outline"
                                            role="combobox"
                                            aria-expanded={branchOpen}
                                            aria-label="Branch for this entry"
                                            disabled={form.processing}
                                            className={cn("h-9 w-full justify-between font-normal", !selectedBranch && "text-muted-foreground")}
                                        >
                                            <span className="truncate">{selectedBranch?.name ?? "Choose branch"}</span>
                                            <ChevronsUpDown className="text-muted-foreground size-4 shrink-0 opacity-50" />
                                        </Button>
                                    } />
                                    <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
                                        <Command>
                                            <CommandInput placeholder="Search branches…" />
                                            <CommandList>
                                                <CommandEmpty>No branches found.</CommandEmpty>
                                                <CommandGroup>
                                                    {branches.map((branch) => (
                                                        <CommandItem
                                                            key={branch.id}
                                                            value={`${branch.name} ${branch.code}`}
                                                            onSelect={() => {
                                                                form.setData("branch_id", branch.id);
                                                                setBranchOpen(false);
                                                            }}
                                                        >
                                                            <Check className={cn("mr-2 size-4", branch.id === form.data.branch_id ? "opacity-100" : "opacity-0")} />
                                                            <span className="truncate">{branch.name}</span>
                                                        </CommandItem>
                                                    ))}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                                {form.errors.branch_id && <p className="text-destructive text-xs">{form.errors.branch_id}</p>}
                            </div>
                            <div className="space-y-1">
                                <label htmlFor={`${source}-intercompany-description`} className="text-sm font-medium">Description</label>
                                <Input id={`${source}-intercompany-description`} value={form.data.description} onChange={(event) => form.setData("description", event.target.value)} />
                                {form.errors.description && <p className="text-destructive text-xs">{form.errors.description}</p>}
                            </div>
                        </div>
                        <JournalLinesEditor lines={form.data.lines} accounts={accounts} errors={form.errors} disabled={form.processing} currency={currency} onChange={(lines) => form.setData("lines", lines)} />
                        {form.errors.lines && <p className="text-destructive text-sm">{form.errors.lines}</p>}
                        <Button type="submit" disabled={form.processing || !canSubmit}>Post intercompany entry</Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
