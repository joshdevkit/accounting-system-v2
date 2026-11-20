# Outgoing Payments — Intercompany Voucher Fixes

**Session date:** 2026-11-14  
**Problem:** Intercompany vouchers on the outgoing payments page showed incorrect status ("Pending" instead of "For Voucher") and cheque was missing from the payment mode list when only vouchers were selected. Client reported: *"wala pang cheque payment sa list ng mode of payment"* (no cheque payment in the payment mode list).  
**Requirement:** Voucher processing must follow the same flow as normal purchases.

---

## Files Changed

| File | Change |
|------|--------|
| `app/Http/Controllers/PurchaseController.php` | Fix voucher status mapping in `outgoing()` method |
| `app/Services/IntercompanyVoucherService.php` | Add `markBatchForRelease()`, fix `payBatch()` for cheque routing; `release()` now handles both cheque and non-cheque (previously cheque-only) |
| `app/Http/Controllers/IntercompanyVoucherController.php` | Update `payBatch` validation & dispatch logic |
| `resources/js/components/outgoing/types.ts` | Add `"for_voucher"` to ProcessingStatus union |
| `resources/js/components/outgoing/status-combobox.tsx` | Add `for_voucher` label, next options, cancel label |
| `resources/js/components/outgoing/intercompany-voucher-row.tsx` | Restrict checkbox eligibility to `for_voucher` only |
| `resources/js/pages/outgoing/Index.tsx` | Show cheque always; unify submit logic; update button text |

---

## Root Causes

### 1. Vouchers showing "pending" instead of "For Voucher"
In `PurchaseController::outgoing()` lines 697–702, the match statement fell through to `default => 'pending'` for `'for_voucher'` status because it wasn't explicitly handled:

```php
// BEFORE (broken)
match (true) {
    $voucher->status === 'for_release' => 'for_release',
    $voucher->status === 'send_for_cheque' && $voucher->check_number => 'for_release',
    $voucher->status === 'send_for_cheque' => 'for_cheque',
    default => 'pending',  // ← 'for_voucher' hit here!
}
```

### 2. Cheque option hidden for vouchers-only selection
In `Index.tsx` lines 220–229, the cheque option was filtered out when no purchases were selected (`dialogEntries.length === 0 && dialogIntercompanyVouchers.length > 0`). This was intentional before but wrong after we unified the flows.

### 3. Voucher checkboxes enabled for non-selectable statuses
`canCheck` in `intercompany-voucher-row.tsx` included `"pending"`, `"for_cheque"`, and `"for_release"`, but these are statuses **after** the voucher has been processed — they should not be batch-selected. Only `for_voucher` is eligible.

### 4. `bank_name`/`check_number` required in `payBatch`
The `payBatch` validation had `required_if:settlement_account,cheque` which blocked batch submission for cheque before cheque details are collected per-row (same as purchases).

---

## Fixes Applied

### Fix 1 — Correct status mapping (`PurchaseController.php`)

```php
// AFTER (correct)
match ($voucher->status) {
    'for_voucher' => 'for_voucher',
    'send_for_cheque' => $voucher->check_number ? 'for_release' : 'for_cheque',
    'for_release' => 'for_release',
    'paid' => 'released',
    default => 'pending',
}
```

Key insight: `send_for_cheque` with a check number = `for_release` (same as purchases where `for_check` + check_number = `for_release`).

### Fix 2 — Show cheque always in batch dialog (`Index.tsx`)

```js
const dialogSources =
    dialogMode === "release"
      ? settlementOptions.filter((o) => o.value !== "cheque")
      : settlementOptions;  // Always available except release mode
```

### Fix 3 — Eligible checkbox selection (`intercompany-voucher-row.tsx`)

```tsx
// Only vouchers in initial for_voucher state can be batch-selected
const canCheck = voucher.processing_status === "for_voucher";
```

### Fix 4 — Relax `payBatch` cheque validation (`IntercompanyVoucherController.php`)

Removed `required_if:settlement_account,cheque` from `bank_name` and `check_number` in `payBatch`. Batch cheque sends vouchers to `send_for_cheque` status; individual cheque details are captured per-row later.

### Fix 5 — Unified `submitPayment` in `Index.tsx`

Both purchases and vouchers now use the same pattern:
- **Cheque selected** → sends to `/purchases/pay-batch` or `/intercompany-vouchers/pay-batch` → status becomes `for_cheque` → user completes cheque details via status menu per row
- **Non-cheque selected** → marks `for_release` directly → release dialog appears
- Button text is always **"Confirm payment"** (not conditional)

### Fix 6 — New service method (`IntercompanyVoucherService.php`)

Added `markBatchForRelease()` matching `PurchasePostingService::markBatchForRelease()`:
```php
public function markBatchForRelease(array $vouchers, string $settlementAccount): void
{
    // Validates all vouchers are in 'for_voucher' status
    // Sets status to 'for_release' with settlement account
}
```

Also updated `payBatch()` to route cheque to `sendForChequeBatch()` instead of instant-post.

### Fix 7 — Status combobox types (`status-combobox.tsx`, `types.ts`)

Added `"for_voucher"` to `ProcessingStatus` union and its `STATUS_LABELS`/`NEXT_STATUSES` entries:
- Label: `"For Voucher"`
- Next options: `["for_voucher", "for_cheque", "cancelled"]`
- Cancel label: `"Move back to Pending"`

---

## Current Payment Flow (Vouchers = Purchases)

| Step | Action | Status | Entry Point |
|------|--------|--------|-------------|
| 1 | Mark purchase/expense For Voucher | `for_voucher` | Purchase detail page |
| 2 | On Outgoing Payments: select rows → click "Proceed to payment" | `for_voucher` | Dialog |
| 3a | Choose cash/bank transfer → "Confirm payment" | → `for_release` | Batch dialog |
| 3b | Choose cheque → "Confirm payment" | → `for_cheque` | Batch dialog |
| 4a | Release dialog → confirm date → post JE | → `paid` | Per-row status menu |
| 4b | Cheque dialog → enter bank/check → save | → `for_release` | Per-row status menu |
| 5 | Status menu → Released → confirm date | → `paid` | Per-row status menu |
| Cancel | Status menu → Cancel payment | → `for_voucher` | Per-row status menu |
| Void | Status menu → Void purchase | → cancelled | Per-row status menu |
| Print | Voucher selected as cheque → "Check Voucher" in release dialog | — | Release dialog (per-row) |

**Printing a cheque voucher** works from the per-row release dialog. Both normal purchases and intercompany vouchers follow the same process — no special case for intercom entries.

---

## Important State Rules

- **Only `for_voucher` vouchers are checkbox-enabled.** Vouchers already at `for_cheque` or `for_release` cannot be re-selected in batch.
- **Cheque collection is per-row**, not batch-level. After batch-send-to-cheque, each row's status menu opens the cheque input dialog.
- **Mixed batches are blocked:** selecting both purchases and vouchers shows an error — process them separately.
- **Vouchers in `send_for_cheque` with no check_number** show "For Cheque" and have a disabled checkbox. Use status menu to fill details.
- **`send_for_cheque` with check_number** shows "For Release" and has a disabled checkbox. Use status menu to release.

---

## Database Schema

`intercompany_vouchers` table columns relevant to workflow:

| Column | Values | Notes |
|--------|--------|-------|
| `status` | `for_voucher`, `send_for_cheque`, `for_release`, `paid` | Controlled by service |
| `settlement_account` | `cash`, `bank_transfer`, `gcash_qrph`, `card`, `cheque` | Set on release/payment |
| `bank_name` / `check_number` | nullable strings | Captured per-row for cheque |
| `payment_journal_entry_id` | FK to `journal_entries` | Set when posted |
| `source_type` | `purchase` or `expense` | Links back to source |
| `source_id` | integer | ID of the source record |

---

## Configuration Reference

`config/accounting.php` — `purchases.settlement_accounts`:

```php
'cash'           => '1101',
'bank_transfer'  => ['01' => '1102-01', '02' => '4201-02'],
'gcash_qrph'     => '1101',
'card'           => '1106',
'cheque'         => ['01' => '1103-01', '02' => '1103-02'],
```

These resolve per-branch via `BranchAccounts::resolve()`. If a branch has no mapping for a settlement type, that option returns null and displays as "Unassigned account".

---

---

## Session 2 — Cheque & Voucher Printing (2026-11-18)

**Requirement:** Both normal purchases and intercompany vouchers on the outgoing payments page must be able to pay via cheque and print vouchers identically — no exception for intercom entries.

### Changes

| File | Change |
|------|--------|
| `app/Services/IntercompanyVoucherService.php` | `release()` now reads `$voucher->settlement_account` instead of always assuming cheque; non-cheque releases pass through cleanly |
| `resources/js/pages/outgoing/Index.tsx` | Release dialog shows "Check Voucher" button when voucher is cheque or has check_number; batch cheque dialog sends `voucher_ids[]` param so backend can route accordingly |
| `app/Http/Controllers/PurchaseController.php` | `batchCheckVoucher()` accepts optional `voucher_ids[]`; when only vouchers are selected calls new `batchVoucherCheckVoucher()` which prints the same overlay using clearing account + settlement account |
| `app/Http/Controllers/IntercompanyVoucherController.php` | `voucher()` endpoint accepts statuses `for_release` and `send_for_cheque` in addition to `for_voucher`/`paid` |

### How it works now

1. **Cheque payment**: select vouchers → "Proceed to payment" → choose **Cheque** → "Confirm payment"
   - Backend routes to `sendForChequeBatch()` → status becomes `for_cheque` / UI shows `for_cheque`
   - Per-row status menu → complete bank/check details → status → `for_release`

2. **Non-cheque payment**: same flow but choose cash/bank transfer → "Confirm payment"
   - Backend routes to `markBatchForRelease()` → status becomes `for_release`

3. **Print cheque voucher**: on the per-row release dialog for a cheque payment, click **"Check Voucher"**
   - Opens `/intercompany-vouchers/{id}/voucher?settlement_account=cheque&payment_date=...`
   - PDF rendered with the same `batch-check-voucher-overlay` template as purchases
   - No distinction between purchase-originated and intercompany-originated vouchers at print time

4. **Backend `batchCheckVoucher()`**: if only `voucher_ids[]` are provided (no purchases), delegates to `batchVoucherCheckVoucher()` which validates branch/payee consistency and renders the overlay. Mixed purchase+voucher selections are still blocked by the frontend (`isMixedBatch`).

### Non-cheque release fix

Before this fix, calling `release()` on a voucher marked For Release with e.g. `bank_transfer` would throw "Enter the cheque number before releasing." because the method unconditionally checked `check_number`. Now it reads `$voucher->settlement_account` and skips the check_number guard for non-cheque sources — matching how `PurchasePostingService::release()` already behaves.
