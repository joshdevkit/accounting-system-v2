import { Head, router, useForm, usePage } from "@inertiajs/react";
import { Check, ChevronsUpDown } from "lucide-react";
import { useState, useEffect, useRef } from "react";
import AuthenticatedLayout from "@/components/layouts/authenticated-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { PaymentActionConfirmationDialog } from "@/components/outgoing/payment-action-confirmation-dialog";
import { IntercompanyVoucherRow } from "@/components/outgoing/intercompany-voucher-row";
import { StatusCombobox } from "@/components/outgoing/status-combobox";
import type {
  OutgoingEntry,
  OutgoingPaymentsProps,
  PaymentConfirmationAction,
  ProcessingStatus,
} from "@/components/outgoing/types";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { formatCents, formatDate } from "@/lib/format";

const todayLocal = () => new Date().toLocaleDateString("en-CA");

export default function OutgoingPayments({
  entries,
  intercompanyVouchers = [],
  settlementOptions = [],
  currency,
}: OutgoingPaymentsProps) {
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [selectedVoucherIds, setSelectedVoucherIds] = useState<number[]>([]);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [dialogTargetIds, setDialogTargetIds] = useState<number[]>([]);
  const [dialogVoucherIds, setDialogVoucherIds] = useState<number[]>([]);
  // "batch" offers every source; "release" (paying a single pending row
  // right away) leaves out cheque, since cheque has its own stages.
  const [dialogMode, setDialogMode] = useState<"batch" | "release">("batch");
  const [chequeTarget, setChequeTarget] = useState<OutgoingEntry | null>(null);
  const [voucherChequeTarget, setVoucherChequeTarget] = useState<number | null>(null);
  const [releaseTarget, setReleaseTarget] = useState<OutgoingEntry | null>(
    null,
  );
  const [voucherReleaseTarget, setVoucherReleaseTarget] = useState<number | null>(null);
  const [confirmationTarget, setConfirmationTarget] = useState<{
    entry: OutgoingEntry;
    action: PaymentConfirmationAction;
  } | null>(null);
  const [voucherConfirmationTarget, setVoucherConfirmationTarget] = useState<{
    voucherId: number;
    payee: string;
    amount_cents: number;
    action: PaymentConfirmationAction;
  } | null>(null);

  const pageErrors =
    usePage<{ errors?: Record<string, string> }>().props.errors ?? {};
  const firstError = Object.values(pageErrors)[0];

  // Only untouched purchases can be batch-paid; the others are already
  // moving through the cheque process.
  const isPayable = (e: OutgoingEntry) =>
    e.processing_status === "pending" || e.processing_status === "for_cheque";
  const selectedEntries = entries.filter((e) => selectedIds.includes(e.id));
  const selectedIntercompanyVouchers = intercompanyVouchers.filter((voucher) =>
    selectedVoucherIds.includes(voucher.id),
  );
  const selectedTotalCents = [...selectedEntries, ...selectedIntercompanyVouchers].reduce(
    (sum, e) => sum + ("net_payable_cents" in e ? e.net_payable_cents : e.amount_cents),
    0,
  );

  const selectable = entries.filter(isPayable);

  const allSelectableSelected =
    selectable.length + intercompanyVouchers.length > 0 &&
    selectable.every((e) => selectedIds.includes(e.id)) &&
    intercompanyVouchers.every((voucher) => selectedVoucherIds.includes(voucher.id));

  const toggleAll = (checked: boolean) => {
    setSelectedIds(checked ? selectable.map((e) => e.id) : []);
    // Don't touch voucher selection here — keep it independent so a
    // "select all purchases" action doesn't accidentally drag in vouchers
    // and hide the cheque source from the batch-dialog.
  };

  const toggleOne = (id: number, checked: boolean) =>
    setSelectedIds((current) =>
      checked ? [...current, id] : current.filter((x) => x !== id),
    );

  const toggleVoucherOne = (id: number, checked: boolean) =>
    setSelectedVoucherIds((current) =>
      checked ? [...current, id] : current.filter((x) => x !== id),
    );

  const payForm = useForm({
    purchase_ids: [] as number[],
    voucher_ids: [] as number[],
    settlement_account: "",
    payment_date: todayLocal(),
  });

  const chequeForm = useForm({ bank_name: "", check_number: "" });
  const voucherChequeForm = useForm({ bank_name: "", check_number: "" });
  const releaseForm = useForm({ payment_date: todayLocal() });
  const voucherReleaseForm = useForm({ payment_date: todayLocal() });
  const confirmationForm = useForm({});

  type FormErrors = Record<string, string | undefined>;
  const payErrors = payForm.errors as FormErrors;
  const chequeErrors = chequeForm.errors as FormErrors;
  const voucherChequeErrors = voucherChequeForm.errors as FormErrors;
  const releaseErrors = releaseForm.errors as FormErrors;
  const voucherReleaseErrors = voucherReleaseForm.errors as FormErrors;

  const openDialogFor = (ids: number[], mode: "batch" | "release") => {
    setDialogTargetIds(ids);
    setDialogVoucherIds([]);
    setDialogMode(mode);
    payForm.setData({
      purchase_ids: ids,
      voucher_ids: [],
      settlement_account: "",
      payment_date: todayLocal(),
    });
    payForm.clearErrors();
    setDialogOpen(true);
  };

  const openSelectedPaymentDialog = () => {
    setDialogTargetIds(selectedIds);
    setDialogVoucherIds(selectedVoucherIds);
    setDialogMode("batch");
    payForm.setData({
      purchase_ids: selectedIds,
      voucher_ids: selectedVoucherIds,
      settlement_account: "",
      payment_date: todayLocal(),
    });
    payForm.clearErrors();
    setDialogOpen(true);
  };

  const openChequeDialog = (entry: OutgoingEntry) => {
    chequeForm.setData({
      bank_name: entry.bank_name ?? "",
      check_number: entry.check_number ?? "",
    });
    chequeForm.clearErrors();
    setChequeTarget(entry);
  };

  const openVoucherChequeDialog = (voucherId: number) => {
    const voucher = intercompanyVouchers.find((v) => v.id === voucherId);
    if (!voucher) return;
    voucherChequeForm.setData({
      bank_name: voucher.bank_name ?? "",
      check_number: voucher.check_number ?? "",
    });
    voucherChequeForm.clearErrors();
    setVoucherChequeTarget(voucherId);
  };

  const openReleaseDialog = (entry: OutgoingEntry) => {
    releaseForm.setData({ payment_date: todayLocal() });
    releaseForm.clearErrors();
    setReleaseTarget(entry);
  };

  const openVoucherReleaseDialog = (voucherId: number) => {
    voucherReleaseForm.setData({ payment_date: todayLocal() });
    voucherReleaseForm.clearErrors();
    setVoucherReleaseTarget(voucherId);
  };

  const openConfirmationDialog = (
    entry: OutgoingEntry,
    action: PaymentConfirmationAction,
  ) => setConfirmationTarget({ entry, action });

  const openVoucherConfirmationDialog = (
    voucherId: number,
    payee: string,
    amount_cents: number,
    action: PaymentConfirmationAction,
  ) => setVoucherConfirmationTarget({ voucherId, payee, amount_cents, action });

  const dialogEntries = entries.filter((e) => dialogTargetIds.includes(e.id));
  const dialogIntercompanyVouchers = intercompanyVouchers.filter((voucher) =>
    dialogVoucherIds.includes(voucher.id),
  );
  const isMixedBatch = dialogEntries.length > 0 && dialogIntercompanyVouchers.length > 0;
  // Show cheque when purchases are included OR only vouchers selected
  // Vouchers follow same process as purchases - can select cheque in dialog
  // Release mode never shows cheque.
  const dialogSources =
    dialogMode === "release"
      ? settlementOptions.filter((o) => o.value !== "cheque")
      : settlementOptions;

  const openVoucherCheckVoucher = () => {
    if (!voucherReleaseTarget) return;
    window.open(`/intercompany-vouchers/${voucherReleaseTarget}/voucher`, "_blank", "noopener,noreferrer");
  };

  const selectedSource = payForm.data.settlement_account;
  const isCheque = selectedSource === "cheque";

  const settlementLabel = settlementOptions.find(
    (o) => o.value === selectedSource,
  )?.label;

  const dialogTotalCents = [...dialogEntries, ...dialogIntercompanyVouchers].reduce(
    (sum, e) => sum + ("net_payable_cents" in e ? e.net_payable_cents : e.amount_cents),
    0,
  );
  const voucherPayees = new Set(
    dialogEntries.map((entry) => entry.vendor_name?.trim().toLowerCase() ?? ""),
  );
  const voucherBranches = new Set(dialogEntries.map((entry) => entry.branch ?? ""));
  const canGenerateVoucher =
    (dialogEntries.length > 0 &&
      voucherPayees.size === 1 &&
      !voucherPayees.has("") &&
      voucherBranches.size === 1 &&
      !voucherBranches.has("")) ||
    (dialogEntries.length === 0 && dialogIntercompanyVouchers.length > 0);

  // One debit row per purchase (Accounts Payable), never merged.
  const debitLines = dialogEntries.map((entry) => ({
    id: entry.id,
    account: entry.payable_account ?? "Unassigned account",
    memo: `${entry.vendor_name ?? "—"} · ${entry.reference_no ?? `#${entry.id}`}`,
    cents: entry.net_payable_cents,
  }));

  const clearingAmounts = new Map<string, number>();
  for (const entry of dialogEntries) {
    const account = entry.clearing_account ?? "Account mapping required";
    clearingAmounts.set(account, (clearingAmounts.get(account) ?? 0) + entry.net_payable_cents);
  }
  const clearingLines = Array.from(clearingAmounts.entries()).map(
    ([account, cents]) => ({ account, cents }),
  );

  const paymentClearingAmounts = new Map(clearingAmounts);
  for (const voucher of dialogIntercompanyVouchers) {
    paymentClearingAmounts.set(
      voucher.clearing_account,
      (paymentClearingAmounts.get(voucher.clearing_account) ?? 0) + voucher.amount_cents,
    );
  }
  const paymentClearingLines = Array.from(paymentClearingAmounts.entries()).map(
    ([account, cents]) => ({ account, cents }),
  );

  // Credits grouped by the resolved settlement account: a Gusto and a
  // Maximilian purchase paid from "Cash" credit different accounts.
  const creditAccount = (
    source: string,
    entryOrVoucher: OutgoingEntry | { settlement_accounts: Record<string, string | null> },
  ) => entryOrVoucher.settlement_accounts[source] ?? "Unassigned account";
  const creditsByAccount = new Map<string, number>();
  for (const entry of dialogEntries) {
    const account = selectedSource ? creditAccount(selectedSource, entry) : (settlementLabel ?? "Payment source (select below)");
    creditsByAccount.set(
      account,
      (creditsByAccount.get(account) ?? 0) + entry.net_payable_cents,
    );
  }
  for (const voucher of dialogIntercompanyVouchers) {
    const account = selectedSource ? creditAccount(selectedSource, voucher) : (settlementLabel ?? "Payment source (select below)");
    creditsByAccount.set(
      account,
      (creditsByAccount.get(account) ?? 0) + voucher.amount_cents,
    );
  }
  const creditLines = Array.from(creditsByAccount.entries()).map(
    ([account, cents]) => ({ account, cents }),
  );

  const submitPayment = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!selectedSource) return;
    if (dialogIntercompanyVouchers.length === 0) {
      // Purchase batch flow (handles cheque per-row via status menu)
      if (selectedSource === "cheque") {
        const pending = selectedEntries.filter((e) => e.processing_status !== "for_cheque");
        if (pending.length > 0) {
          router.post("/purchases/pay-batch", { purchase_ids: selectedIds, settlement_account: "cheque", payment_date: payForm.data.payment_date }, {
            onSuccess: () => { setDialogOpen(false); setSelectedIds([]); },
          });
        } else {
          setDialogOpen(false); setSelectedIds([]);
        }
      } else {
        router.post("/purchases/pay-batch", { purchase_ids: selectedIds, settlement_account: selectedSource, payment_date: payForm.data.payment_date }, {
          onSuccess: () => { setDialogOpen(false); setSelectedIds([]); },
        });
      }
    } else {
      // Voucher batch flow - same process as purchases (can be cheque or direct payment)
      router.post("/intercompany-vouchers/pay-batch", { voucher_ids: selectedVoucherIds, settlement_account: selectedSource, payment_date: payForm.data.payment_date }, {
        onSuccess: () => {
          setDialogOpen(false);
          setSelectedVoucherIds([]);
        },
      });
    }
  };

  const openBatchCheckVoucher = () => {
    const params = new URLSearchParams();
    dialogTargetIds.forEach((id) => params.append("purchase_ids[]", String(id)));
    dialogVoucherIds.forEach((id) => params.append("voucher_ids[]", String(id)));
    if (selectedSource) params.set("settlement_account", selectedSource);
    if (payForm.data.payment_date) params.set("payment_date", payForm.data.payment_date);
    window.open(`/purchases/batch-check-voucher?${params.toString()}`, "_blank", "noopener,noreferrer");
  };

  const submitCheque = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!chequeTarget) return;
    chequeForm.post(`/purchases/${chequeTarget.id}/cheque-details`, {
      onSuccess: () => setChequeTarget(null),
    });
  };

  const submitVoucherCheque = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (voucherChequeTarget == null) return;
    voucherChequeForm.post(`/intercompany-vouchers/${voucherChequeTarget}/cheque-details`, {
      onSuccess: () => setVoucherChequeTarget(null),
    });
  };

  const submitRelease = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!releaseTarget) return;
    releaseForm.post(`/purchases/${releaseTarget.id}/release`, {
      onSuccess: () => setReleaseTarget(null),
    });
  };

  const submitVoucherRelease = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (voucherReleaseTarget == null) return;
    voucherReleaseForm.post(`/intercompany-vouchers/${voucherReleaseTarget}/release`, {
      onSuccess: () => setVoucherReleaseTarget(null),
    });
  };

  const submitConfirmation = (): void => {
    if (!confirmationTarget) return;
    const { entry, action } = confirmationTarget;
    const endpoint = action === "void_purchase"
      ? `/purchases/${entry.id}/void`
      : `/purchases/${entry.id}/cancel-payment`;

    confirmationForm.post(endpoint, {
      onSuccess: () => setConfirmationTarget(null),
    });
  };

  const submitVoucherConfirmation = (): void => {
    if (!voucherConfirmationTarget) return;
    const { voucherId, action } = voucherConfirmationTarget;
    const endpoint = action === "void_purchase"
      ? `/intercompany-vouchers/${voucherId}/void`
      : `/intercompany-vouchers/${voucherId}/cancel-payment`;
    confirmationForm.post(endpoint, {
      onSuccess: () => setVoucherConfirmationTarget(null),
    });
  };

  const handleStatusChange = (entry: OutgoingEntry, next: ProcessingStatus) => {
    // Picking the current status does nothing, except For Release,
    // which reopens the cheque details for editing.
    if (next === entry.processing_status && next !== "for_release") return;

    switch (next) {
      case "for_cheque":
        router.post(`/purchases/${entry.id}/for-cheque`, {});
        break;
      case "for_release":
        if (entry.settlement_account === "cheque") {
          openChequeDialog(entry);
        } else {
          openReleaseDialog(entry);
        }
        break;
      case "released":
        if (entry.processing_status === "pending") {
          openDialogFor([entry.id], "release");
        } else {
          openReleaseDialog(entry);
        }
        break;
      case "pending":
        openConfirmationDialog(entry, "cancel_payment");
        break;
      case "cancelled":
        openConfirmationDialog(entry, "void_purchase");
        break;
    }
  };

  const handleVoucherStatusChange = (voucherId: number, next: ProcessingStatus, bankName?: string | null, checkNumber?: string | null) => {
    const voucher = intercompanyVouchers.find((v) => v.id === voucherId);
    const payee = voucher?.payee ?? "";
    const amountCents = voucher?.amount_cents ?? 0;
    if (next === "for_cheque") {
      router.post(`/intercompany-vouchers/${voucherId}/send-for-cheque`, {});
    } else if (next === "for_release") {
      if (checkNumber) {
        router.post(`/intercompany-vouchers/${voucherId}/cheque-details`, { bank_name: bankName ?? "", check_number: checkNumber });
      } else {
        openVoucherChequeDialog(voucherId);
      }
    } else if (next === "released") {
      openVoucherReleaseDialog(voucherId);
    } else if (next === "pending") {
      openVoucherConfirmationDialog(voucherId, payee, amountCents, "cancel_payment");
    } else if (next === "for_voucher") {
      // Move back to pending - same as cancel payment
      openVoucherConfirmationDialog(voucherId, payee, amountCents, "cancel_payment");
    }
  };

  const voucherHandlerRef = useRef(handleVoucherStatusChange);
  voucherHandlerRef.current = handleVoucherStatusChange;
  useEffect(() => {
    const listener = (event: Event) => {
      const detail = (event as CustomEvent<{ id: number; next: ProcessingStatus }>).detail;
      voucherHandlerRef.current(detail.id, detail.next);
    };
    window.addEventListener("voucher-status-change", listener);
    return () => window.removeEventListener("voucher-status-change", listener);
  }, []);

  const dialogError =
    payErrors.status ?? payErrors.settlement_account ?? payErrors.purchase_ids ?? payErrors.voucher_ids;

  return (
    <AuthenticatedLayout
      title="Outgoing Payments"
      description="Posted purchases and intercompany vouchers awaiting payment"
    >
      <Head title="Outgoing Payments" />

      <div className="space-y-4">
        {firstError &&
          !dialogOpen &&
          !chequeTarget &&
          !releaseTarget &&
          !confirmationTarget && (
          <div className="border-destructive/40 text-destructive rounded-md border px-4 py-2 text-sm">
            {firstError}
          </div>
        )}

        {selectedIds.length + selectedVoucherIds.length > 0 && (
          <Card className="border-primary/40">
            <CardContent className="flex flex-wrap items-center justify-between gap-3 py-3">
              <div className="text-sm">
                <span className="font-medium">
                  {selectedIds.length + selectedVoucherIds.length} selected
                </span>
                <span className="text-muted-foreground">
                  {" "}
                  · Total {formatCents(selectedTotalCents, currency)}
                </span>
              </div>
              <Button
                size="sm"
                onClick={openSelectedPaymentDialog}
              >
                Proceed to payment
              </Button>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10">
                    <Checkbox
                      label="Select all"
                      checked={allSelectableSelected}
                      onCheckedChange={(checked) => toggleAll(checked)}
                      disabled={selectable.length + intercompanyVouchers.length === 0}
                    />
                  </TableHead>
                  <TableHead>Vendor</TableHead>
                  <TableHead>Reference</TableHead>
                  <TableHead>Due</TableHead>
                  <TableHead>Branch</TableHead>
                  <TableHead>Payable account</TableHead>
                  <TableHead>Cheque</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {intercompanyVouchers.map((voucher) => (
                  <IntercompanyVoucherRow
                    key={`intercompany-${voucher.id}`}
                    voucher={voucher}
                    checked={selectedVoucherIds.includes(voucher.id)}
                    onCheckedChange={(checked) => toggleVoucherOne(voucher.id, checked)}
                    currency={currency}
                  />
                ))}
                {entries.map((entry) => (
                  <TableRow key={entry.id}>
                    <TableCell>
                      <Checkbox
                        label=""
                        aria-label={`Select ${entry.vendor_name ?? `purchase ${entry.id}`}`}
                        checked={selectedIds.includes(entry.id)}
                        onCheckedChange={(checked) =>
                          toggleOne(entry.id, checked)
                        }
                        disabled={!isPayable(entry)}
                      />
                    </TableCell>
                    <TableCell>
                      <a
                        href={`/purchases/${entry.id}`}
                        className="hover:underline"
                      >
                        {entry.vendor_name ?? "—"}
                      </a>
                    </TableCell>
                    <TableCell>{entry.reference_no ?? "—"}</TableCell>
                    <TableCell>
                      {entry.due_date ? formatDate(entry.due_date) : "—"}
                    </TableCell>
                    <TableCell>{entry.branch ?? "Company-wide"}</TableCell>
                    <TableCell className="text-muted-foreground font-mono text-xs">
                      {entry.payable_account ?? "—"}
                    </TableCell>
                    <TableCell className="text-xs">
                      {entry.check_number
                        ? `${entry.bank_name ?? ""} ${entry.check_number}`.trim()
                        : "—"}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                      {formatCents(entry.net_payable_cents, currency)}
                    </TableCell>
                    <TableCell>
                      <StatusCombobox
                        value={entry.processing_status}
                        onChange={(next) => handleStatusChange(entry, next)}
                      />
                    </TableCell>
                  </TableRow>
                ))}
                {entries.length === 0 && intercompanyVouchers.length === 0 && (
                  <TableRow>
                    <TableCell
                      colSpan={9}
                      className="text-muted-foreground py-8 text-center"
                    >
                      Nothing waiting on payment.
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>

      {/* Batch payment dialog */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Process payment</DialogTitle>
            <DialogDescription>
              {dialogEntries.length} entr
              {dialogEntries.length === 1 ? "y" : "ies"} · Total{" "}
              {formatCents(dialogTotalCents, currency)}
              {" · Confirming moves the selection to For Release; journals post when released."}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <ul className="text-muted-foreground space-y-1 text-sm">
              {dialogEntries.map((entry) => (
                <li
                  key={entry.id}
                  className="flex items-center justify-between"
                >
                  <span>
                    {entry.vendor_name ?? "—"} ·{" "}
                    {entry.reference_no ?? `#${entry.id}`}
                  </span>
                  <span className="tabular-nums">
                    {formatCents(entry.net_payable_cents, currency)}
                  </span>
                </li>
              ))}
              {dialogIntercompanyVouchers.map((voucher) => (
                <li
                  key={`intercompany-${voucher.id}`}
                  className="flex items-center justify-between"
                >
                  <span>
                    {voucher.payee} · {voucher.source_reference ?? `#${voucher.source_id}`}
                    <span className="ml-1 text-xs">(Intercompany)</span>
                  </span>
                  <span className="tabular-nums">
                    {formatCents(voucher.amount_cents, currency)}
                  </span>
                </li>
              ))}
            </ul>

            {dialogEntries.length > 0 && (
            <div className="space-y-2">
              <h3 className="text-sm font-medium">A/P clearing entry</h3>
              <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs">
                    <th className="px-4 py-2 font-medium">Account</th>
                    <th className="px-4 py-2 text-right font-medium">Debit</th>
                    <th className="px-4 py-2 text-right font-medium">Credit</th>
                  </tr>
                </thead>
                <tbody>
                  {debitLines.map((line) => (
                    <tr key={line.id} className="border-b last:border-0">
                      <td className="px-4 py-2">
                        <div className="font-mono text-xs">{line.account}</div>
                        <div className="text-muted-foreground text-xs">
                          {line.memo}
                        </div>
                      </td>
                      <td className="px-4 py-2 text-right tabular-nums">
                        {formatCents(line.cents, currency)}
                      </td>
                      <td className="px-4 py-2 text-right tabular-nums">—</td>
                    </tr>
                  ))}
                  {clearingLines.map((line) => (
                    <tr key={line.account} className="border-b last:border-0">
                      <td className="px-4 py-2 font-mono text-xs">
                        {line.account}
                      </td>
                      <td className="px-4 py-2 text-right tabular-nums">—</td>
                      <td className="px-4 py-2 text-right tabular-nums">
                        {formatCents(line.cents, currency)}
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="bg-muted/20 border-t">
                    <td className="px-4 py-2 text-right font-medium">Total</td>
                    <td className="px-4 py-2 text-right font-semibold tabular-nums">
                      {formatCents(dialogTotalCents, currency)}
                    </td>
                    <td className="px-4 py-2 text-right font-semibold tabular-nums">
                      {formatCents(dialogTotalCents, currency)}
                    </td>
                  </tr>
                </tfoot>
              </table>
              </div>
            </div>
            )}

            {dialogIntercompanyVouchers.length > 0 && (
              <p className="text-muted-foreground text-xs">
                A/P clearing was already posted with the intercompany clearing entry. This payment only clears A/P Clearing against the selected payment source.
              </p>
            )}

            <div className="space-y-2">
              <h3 className="text-sm font-medium">Payment entry</h3>
              <div className="overflow-x-auto rounded-md border">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs">
                      <th className="px-4 py-2 font-medium">Account</th>
                      <th className="px-4 py-2 text-right font-medium">Debit</th>
                      <th className="px-4 py-2 text-right font-medium">Credit</th>
                    </tr>
                  </thead>
                  <tbody>
                    {paymentClearingLines.map((line) => (
                      <tr key={line.account} className="border-b last:border-0">
                        <td className="px-4 py-2 font-mono text-xs">{line.account}</td>
                        <td className="px-4 py-2 text-right tabular-nums">{formatCents(line.cents, currency)}</td>
                        <td className="px-4 py-2 text-right tabular-nums">—</td>
                      </tr>
                    ))}
                    {creditLines.map((line) => (
                      <tr key={line.account} className="border-b last:border-0">
                        <td className="px-4 py-2 font-mono text-xs">{line.account}</td>
                        <td className="px-4 py-2 text-right tabular-nums">—</td>
                        <td className="px-4 py-2 text-right tabular-nums">{formatCents(line.cents, currency)}</td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot>
                    <tr className="bg-muted/20 border-t">
                      <td className="px-4 py-2 text-right font-medium">Total</td>
                      <td className="px-4 py-2 text-right font-semibold tabular-nums">{formatCents(dialogTotalCents, currency)}</td>
                      <td className="px-4 py-2 text-right font-semibold tabular-nums">{formatCents(dialogTotalCents, currency)}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            {isCheque && (
              <p className="text-muted-foreground text-xs">
                Cheque payments are only posted to the books when released.
                Selected items move to "For Cheque" status; complete cheque
                details from each row's status menu before releasing.
              </p>
            )}

            {isMixedBatch && (
              <p className="text-destructive text-sm">
                Process purchases and intercompany vouchers in separate batches. They follow different posting flows.
              </p>
            )}

            <form
              id="pay-batch-form"
              onSubmit={submitPayment}
              className="grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
              <div className="min-w-0 space-y-1">
                <label className="text-muted-foreground text-xs">
                  Paid from
                </label>
                <Popover>
                  <PopoverTrigger
                    render={
                      <Button
                        type="button"
                        variant="outline"
                        role="combobox"
                        className="w-full min-w-0 justify-between font-normal"
                      />
                    }
                  >
                    {settlementLabel ?? "Choose a source…"}
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                  </PopoverTrigger>
                  <PopoverContent className="w-[var(--anchor-width)] p-0">
                    <Command>
                      <CommandInput placeholder="Search account..." />
                      <CommandList>
                        <CommandEmpty>No account found.</CommandEmpty>
                        <CommandGroup>
                          {dialogSources.map((option) => (
                            <CommandItem
                              key={option.value}
                              value={option.label}
                              onSelect={() =>
                                payForm.setData(
                                  "settlement_account",
                                  option.value,
                                )
                              }
                            >
                              <Check
                                className={
                                  selectedSource === option.value
                                    ? "mr-2 size-4 opacity-100"
                                    : "mr-2 size-4 opacity-0"
                                }
                              />
                              {option.label}
                            </CommandItem>
                          ))}
                        </CommandGroup>
                      </CommandList>
                    </Command>
                  </PopoverContent>
                </Popover>
              </div>

              {selectedSource && !isCheque && (
                <div className="min-w-0 space-y-1">
                  <label className="text-muted-foreground text-xs" htmlFor="payment-date">
                    Payment date (Optional set to current date)
                  </label>
                  <Input
                    id="payment-date"
                    type="date"
                    value={payForm.data.payment_date}
                    onChange={(e) =>
                      payForm.setData("payment_date", e.target.value)
                    }
                    className="w-full min-w-0"
                  />
                  {payErrors.payment_date && (
                    <p className="text-destructive text-xs">
                      {payErrors.payment_date}
                    </p>
                  )}
                </div>
              )}

              {dialogError && (
                <p className="text-destructive text-xs">{dialogError}</p>
              )}
              {!canGenerateVoucher && dialogEntries.length > 0 && dialogIntercompanyVouchers.length === 0 && (
                <p className="text-muted-foreground text-xs">
                  A cheque voucher requires purchases for the same payee and branch.
                </p>
              )}
            </form>
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="ghost"
              onClick={() => setDialogOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={openBatchCheckVoucher}
              disabled={!canGenerateVoucher}
            >
              Cheque Voucher
            </Button>
            <Button
              type="submit"
              form="pay-batch-form"
              disabled={payForm.processing || !selectedSource || isMixedBatch}
            >
              Confirm payment
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Cheque details dialog (For Cheque → For Release) */}
      <Dialog
        open={!!chequeTarget}
        onOpenChange={(open) => !open && setChequeTarget(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Cheque details</DialogTitle>
            <DialogDescription>
              {chequeTarget?.vendor_name ?? "—"} ·{" "}
              {chequeTarget &&
                formatCents(chequeTarget.net_payable_cents, currency)}
            </DialogDescription>
          </DialogHeader>
          <form id="cheque-form" onSubmit={submitCheque} className="space-y-3">
            <div className="space-y-1">
              <label className="text-muted-foreground text-xs">Bank</label>
              <Input
                value={chequeForm.data.bank_name}
                onChange={(e) =>
                  chequeForm.setData("bank_name", e.target.value)
                }
                placeholder="e.g. BDO"
                className="w-full"
              />
              {chequeForm.errors.bank_name && (
                <p className="text-destructive text-xs">
                  {chequeForm.errors.bank_name}
                </p>
              )}
            </div>
            <div className="space-y-1">
              <label className="text-muted-foreground text-xs">
                Cheque no.
              </label>
              <Input
                value={chequeForm.data.check_number}
                onChange={(e) =>
                  chequeForm.setData("check_number", e.target.value)
                }
                placeholder="e.g. 0001234"
                className="w-full"
              />
              {chequeForm.errors.check_number && (
                <p className="text-destructive text-xs">
                  {chequeForm.errors.check_number}
                </p>
              )}
            </div>
            {chequeErrors.status && (
              <p className="text-destructive text-xs">{chequeErrors.status}</p>
            )}
          </form>
          <DialogFooter>
            <Button
              type="button"
              variant="ghost"
              onClick={() => setChequeTarget(null)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              form="cheque-form"
              disabled={chequeForm.processing}
            >
              Save
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Release dialog (For Release → Released, posts the journal) */}
      <Dialog
        open={!!releaseTarget}
        onOpenChange={(open) => !open && setReleaseTarget(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Release payment</DialogTitle>
            <DialogDescription>
              Confirm the payment has been released. This posts the payment to
              the books.
            </DialogDescription>
          </DialogHeader>
          {releaseTarget && (
            <div className="bg-muted/20 space-y-1 rounded-md border p-3 text-sm">
              <div className="flex justify-between">
                <span>{releaseTarget.vendor_name ?? "—"}</span>
                <span className="tabular-nums">
                  {formatCents(releaseTarget.net_payable_cents, currency)}
                </span>
              </div>
              <div className="text-muted-foreground text-xs">
                Paid from: {releaseTarget.settlement_accounts[releaseTarget.settlement_account ?? ""] ?? releaseTarget.settlement_account ?? "—"}
                {releaseTarget.bank_name && (
                  <>
                    {" · "}
                    {releaseTarget.bank_name}
                    {" · "}
                    {releaseTarget.check_number ?? "No cheque no."}
                  </>
                )}
              </div>
            </div>
          )}
          <form
            id="release-form"
            onSubmit={submitRelease}
            className="space-y-3"
          >
            <div className="space-y-1">
              <label className="text-muted-foreground text-xs">
                Date released
              </label>
              <Input
                type="date"
                value={releaseForm.data.payment_date}
                onChange={(e) =>
                  releaseForm.setData("payment_date", e.target.value)
                }
                className="w-full"
              />
            </div>
            {(releaseErrors.status ??
              releaseErrors.check_number ??
              releaseErrors.settlement_account) && (
              <p className="text-destructive text-xs">
                {releaseErrors.status ??
                  releaseErrors.check_number ??
                  releaseErrors.settlement_account}
              </p>
            )}
          </form>
          <DialogFooter>
            <Button
              type="button"
              variant="ghost"
              onClick={() => setReleaseTarget(null)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              form="release-form"
              disabled={releaseForm.processing}
            >
              Confirm release
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Voucher cheque details dialog (For Cheque → For Release) */}
      <Dialog
        open={voucherChequeTarget != null}
        onOpenChange={(open) => !open && setVoucherChequeTarget(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Cheque details</DialogTitle>
            <DialogDescription>
              {intercompanyVouchers.find((v) => v.id === voucherChequeTarget)?.payee ?? "—"} ·{" "}
              {voucherChequeTarget != null &&
                formatCents(
                  intercompanyVouchers.find((v) => v.id === voucherChequeTarget)?.amount_cents ?? 0,
                  currency,
                )}
            </DialogDescription>
          </DialogHeader>
          <form id="voucher-cheque-form" onSubmit={submitVoucherCheque} className="space-y-3">
            <div className="space-y-1">
              <label className="text-muted-foreground text-xs">Bank</label>
              <Input
                value={voucherChequeForm.data.bank_name}
                onChange={(e) =>
                  voucherChequeForm.setData("bank_name", e.target.value)
                }
                placeholder="e.g. BDO"
                className="w-full"
              />
              {voucherChequeErrors.bank_name && (
                <p className="text-destructive text-xs">
                  {voucherChequeErrors.bank_name}
                </p>
              )}
            </div>
            <div className="space-y-1">
              <label className="text-muted-foreground text-xs">
                Cheque no.
              </label>
              <Input
                value={voucherChequeForm.data.check_number}
                onChange={(e) =>
                  voucherChequeForm.setData("check_number", e.target.value)
                }
                placeholder="e.g. 0001234"
                className="w-full"
              />
              {voucherChequeErrors.check_number && (
                <p className="text-destructive text-xs">
                  {voucherChequeErrors.check_number}
                </p>
              )}
            </div>
            {voucherChequeErrors.voucher_ids && (
              <p className="text-destructive text-xs">
                {voucherChequeErrors.voucher_ids}
              </p>
            )}
          </form>
          <DialogFooter>
            <Button
              type="button"
              variant="ghost"
              onClick={() => setVoucherChequeTarget(null)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              form="voucher-cheque-form"
              disabled={voucherChequeForm.processing}
            >
              Save
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Voucher release dialog (For Release → Released, posts the journal) */}
      <Dialog
        open={voucherReleaseTarget != null}
        onOpenChange={(open) => !open && setVoucherReleaseTarget(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Release payment</DialogTitle>
            <DialogDescription>
              Confirm the payment has been released. This posts the payment to
              the books.
            </DialogDescription>
          </DialogHeader>
          {voucherReleaseTarget != null &&
            (() => {
              const voucher = intercompanyVouchers.find((v) => v.id === voucherReleaseTarget);
              return (
                <div className="bg-muted/20 space-y-1 rounded-md border p-3 text-sm">
                  <div className="flex justify-between">
                    <span>{voucher?.payee ?? "—"}</span>
                    <span className="tabular-nums">
                      {formatCents(voucher?.amount_cents ?? 0, currency)}
                    </span>
                  </div>
                  <div className="text-muted-foreground text-xs">
                    Paid from: {voucher?.settlement_accounts[voucher?.settlement_account ?? ""] ?? voucher?.settlement_account ?? "Cheque"}
                    {voucher?.bank_name && (
                      <>
                        {" · "}
                        {voucher.bank_name}
                        {" · "}
                        {voucher.check_number ?? "No cheque no."}
                      </>
                    )}
                  </div>
                </div>
              );
            })()}
          <form
            id="voucher-release-form"
            onSubmit={submitVoucherRelease}
            className="space-y-3"
          >
            <div className="space-y-1">
              <label className="text-muted-foreground text-xs">
                Date released
              </label>
              <Input
                type="date"
                value={voucherReleaseForm.data.payment_date}
                onChange={(e) =>
                  voucherReleaseForm.setData("payment_date", e.target.value)
                }
                className="w-full"
              />
            </div>
            {(voucherReleaseErrors.voucher_ids ?? voucherReleaseErrors.status) && (
              <p className="text-destructive text-xs">
                {voucherReleaseErrors.voucher_ids ?? voucherReleaseErrors.status}
              </p>
            )}
          </form>
          <DialogFooter>
            <Button
              type="button"
              variant="ghost"
              onClick={() => setVoucherReleaseTarget(null)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              form="voucher-release-form"
              disabled={voucherReleaseForm.processing}
            >
              Confirm release
            </Button>
            {voucherReleaseTarget != null &&
              (() => {
                const v = intercompanyVouchers.find((x) => x.id === voucherReleaseTarget);
                return v && (v.settlement_account === "cheque" || v.check_number) ? (
                  <Button type="button" variant="outline" onClick={openVoucherCheckVoucher}>
                    Check Voucher
                  </Button>
                ) : null;
              })()}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Voucher cancel-payment confirmation */}
      <Dialog
        open={!!voucherConfirmationTarget}
        onOpenChange={(open) => !open && setVoucherConfirmationTarget(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Cancel payment?</DialogTitle>
            <DialogDescription>
              The voucher will return to For Voucher, and any cheque details will be cleared.
            </DialogDescription>
          </DialogHeader>
          {voucherConfirmationTarget && (
            <div className="bg-muted/20 space-y-1 rounded-md border p-3 text-sm">
              <div>{voucherConfirmationTarget.payee}</div>
              <div className="text-muted-foreground text-xs">
                {formatCents(voucherConfirmationTarget.amount_cents, currency)}
              </div>
            </div>
          )}
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setVoucherConfirmationTarget(null)}
              disabled={confirmationForm.processing}
            >
              Keep payment
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={submitVoucherConfirmation}
              disabled={confirmationForm.processing || !voucherConfirmationTarget}
            >
              {confirmationForm.processing ? "Processing…" : "Cancel payment"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PaymentActionConfirmationDialog
        open={!!confirmationTarget}
        action={confirmationTarget?.action ?? null}
        entry={confirmationTarget?.entry ?? null}
        processing={confirmationForm.processing}
        onOpenChange={(open) => !open && setConfirmationTarget(null)}
        onConfirm={submitConfirmation}
      />
    </AuthenticatedLayout>
  );
}
