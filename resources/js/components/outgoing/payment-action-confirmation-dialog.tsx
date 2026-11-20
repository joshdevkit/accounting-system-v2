import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type {
  OutgoingEntry,
  PaymentConfirmationAction,
} from "./types";

interface PaymentActionConfirmationDialogProps {
  open: boolean;
  action: PaymentConfirmationAction | null;
  entry: OutgoingEntry | null;
  processing: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: () => void;
}

export function PaymentActionConfirmationDialog({
  open,
  action,
  entry,
  processing,
  onOpenChange,
  onConfirm,
}: PaymentActionConfirmationDialogProps) {
  const isVoid = action === "void_purchase";
  const actionLabel = isVoid ? "Void purchase" : "Cancel payment";
  const closeLabel = isVoid ? "Keep purchase" : "Keep payment";

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{actionLabel}?</DialogTitle>
          <DialogDescription>
            {isVoid
              ? "This will void the purchase and reverse its journal entry."
              : "The purchase will return to Pending, and any cheque details will be cleared."}
          </DialogDescription>
        </DialogHeader>
        {entry && (
          <div className="bg-muted/20 space-y-1 rounded-md border p-3 text-sm">
            <div>{entry.vendor_name ?? "—"}</div>
            <div className="text-muted-foreground text-xs">
              {entry.reference_no ?? `Purchase #${entry.id}`}
            </div>
          </div>
        )}
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={processing}
          >
            {closeLabel}
          </Button>
          <Button
            type="button"
            variant="destructive"
            onClick={onConfirm}
            disabled={processing || !entry || !action}
          >
            {processing ? "Processing…" : actionLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
