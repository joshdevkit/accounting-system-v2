<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Account;
use App\Support\BranchAccounts;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasePostingService
{
    public function __construct(
        protected JournalEntryService $journalEntries,
    ) {}

    public function computeLineVat(float $amount, string $taxType): float
    {
        $vatRate = (float) config('accounting.purchases.vat_rate', 0.12);

        return $taxType === 'vatable' ? round($amount * $vatRate, 2) : 0.0;
    }

    public function computeLineWithholding(float $amount, float $withholdingRate): float
    {
        return $withholdingRate > 0 ? round($amount * ($withholdingRate / 100), 2) : 0.0;
    }

    public function post(Purchase $purchase, ?User $user = null): JournalEntry
    {
        if ($purchase->status === 'posted') {
            throw ValidationException::withMessages([
                'status' => 'This purchase has already been posted.',
            ]);
        }

        $lines = $this->lines($purchase);

        return DB::transaction(function () use ($purchase, $lines, $user) {
            $entry = $this->journalEntries->store(
                $purchase->company,
                [
                    'branch_id' => $purchase->branch_id,
                    'transaction_date' => $purchase->purchase_date->toDateString(),
                    'description' => $purchase->description
                        ?: ($purchase->vendorDisplayName() ? "Purchase — {$purchase->vendorDisplayName()}" : 'Purchase'),
                    'source_type' => 'purchase',
                    'source_reference' => $purchase->reference_no,
                ],
                $lines,
                $user,
                post: true,
            );

            $purchase->fill([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
                'posted_by' => $user?->id,
                'posted_at' => now(),
            ])->save();

            return $entry;
        });
    }

    public function pay(
        Purchase $purchase,
        string $settlementAccount,
        ?User $user = null,
        mixed $date = null,
        ?string $bankName = null,
        ?string $checkNumber = null,
    ): JournalEntry {
        return $this->payBatch(
            [$purchase],
            $settlementAccount,
            $user,
            $date,
            $bankName,
            $checkNumber,
        );
    }

    /**
     * @param  list<Purchase>  $purchases
     */
    public function markBatchForRelease(array $purchases, string $settlementAccount): void
    {
        if ($purchases === []) {
            throw ValidationException::withMessages([
                'purchase_ids' => 'Select at least one purchase to process.',
            ]);
        }

        if ($settlementAccount === 'cheque') {
            throw ValidationException::withMessages([
                'settlement_account' => 'Cheque payments must be sent through the cheque workflow.',
            ]);
        }

        DB::transaction(function () use ($purchases, $settlementAccount) {
            $purchaseIds = collect($purchases)->pluck('id')->unique()->values();
            $lockedPurchases = Purchase::query()
                ->with(['branch', 'company'])
                ->whereKey($purchaseIds)
                ->lockForUpdate()
                ->get();

            if ($lockedPurchases->count() !== $purchaseIds->count()) {
                throw ValidationException::withMessages([
                    'purchase_ids' => 'Some selected purchases are no longer available.',
                ]);
            }

            $config = (array) config('accounting.purchases');
            $settlementConfig = $config['settlement_accounts'][$settlementAccount] ?? null;
            if (! ($config['clearing_account'] ?? null) || ! $settlementConfig) {
                throw ValidationException::withMessages([
                    'settlement_account' => 'Payment, payable, or A/P Clearing account mapping is missing.',
                ]);
            }

            foreach ($lockedPurchases as $purchase) {
                if (
                    ! $purchase->canMarkPaid()
                    || ! in_array($purchase->payment_status, [null, 'unpaid', 'for_check'], true)
                ) {
                    throw ValidationException::withMessages([
                        'status' => "Purchase #{$purchase->id} is no longer awaiting payment.",
                    ]);
                }

                if ($purchase->payment_status === 'for_check' && $purchase->check_number) {
                    throw ValidationException::withMessages([
                        'status' => "Purchase #{$purchase->id} already has a cheque number. Release it from its status menu instead.",
                    ]);
                }

                $this->payableAccount($purchase);
                $this->branchAccount($purchase, $config['clearing_account'], 'clearing_account');
                $this->branchAccount($purchase, $settlementConfig, 'settlement_account');
                $purchase->fill([
                    'payment_status' => 'for_release',
                    'settlement_account' => $settlementAccount,
                    'bank_name' => null,
                    'check_number' => null,
                ])->save();
            }
        });
    }

    /**
     * @param  list<Purchase>  $purchases
     */
    public function payBatch(
        array $purchases,
        string $settlementAccount,
        ?User $user = null,
        mixed $date = null,
        ?string $bankName = null,
        ?string $checkNumber = null,
    ): JournalEntry {
        if ($purchases === []) {
            throw ValidationException::withMessages([
                'purchase_ids' => 'Select at least one purchase to pay.',
            ]);
        }

        $transactionDate = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        return DB::transaction(function () use ($purchases, $settlementAccount, $user, $transactionDate, $bankName, $checkNumber) {
            $purchaseIds = collect($purchases)->pluck('id')->unique()->values();
            $lockedPurchases = Purchase::query()
                ->with(['branch', 'company'])
                ->whereKey($purchaseIds)
                ->lockForUpdate()
                ->get();

            if ($lockedPurchases->count() !== $purchaseIds->count()) {
                throw ValidationException::withMessages([
                    'purchase_ids' => 'Some selected purchases are no longer available.',
                ]);
            }

            $config = (array) config('accounting.purchases');
            $settlementConfig = $config['settlement_accounts'][$settlementAccount] ?? null;
            $clearingConfig = $config['clearing_account'] ?? null;

            if (! $settlementConfig || ! $clearingConfig) {
                throw ValidationException::withMessages([
                    'settlement_account' => 'Payment, payable, or A/P Clearing account mapping is missing.',
                ]);
            }

            $clearLines = [];
            $paymentLines = [];
            $clearingAmounts = [];
            $settlementAmounts = [];
            $paymentData = [];

            foreach ($lockedPurchases as $purchase) {
                if ($purchase->status !== 'posted' || ! $purchase->canMarkPaid()) {
                    throw ValidationException::withMessages([
                        'status' => "Purchase #{$purchase->id} is no longer awaiting payment.",
                    ]);
                }

                $purchase->loadMissing('lines');
                $payable = $this->payableAccount($purchase);
                $clearing = $this->branchAccount($purchase, $clearingConfig);
                $settlement = $this->branchAccount($purchase, $settlementConfig, 'settlement_account');
                $amount = Money::cents($purchase->netPayable());
                $memo = $purchase->vendorDisplayName()
                    ? "Payment — {$purchase->vendorDisplayName()}"
                    : "Purchase payment #{$purchase->id}";

                $clearLines[] = $this->line($payable->id, $amount, 0, $memo);
                $clearingAmounts[$clearing->id] = ($clearingAmounts[$clearing->id] ?? 0) + $amount;
                $settlementAmounts[$settlement->id] = ($settlementAmounts[$settlement->id] ?? 0) + $amount;
                $paymentData[] = $purchase;
            }

            foreach ($clearingAmounts as $accountId => $amount) {
                $clearLines[] = $this->line((int) $accountId, 0, $amount, 'A/P Clearing');
                $paymentLines[] = $this->line((int) $accountId, $amount, 0, 'A/P Clearing');
            }

            foreach ($settlementAmounts as $accountId => $amount) {
                $paymentLines[] = $this->line((int) $accountId, 0, $amount, 'Payment settlement');
            }

            $branchIds = $lockedPurchases->pluck('branch_id')->unique();
            $references = 'Purchase IDs: '.$purchaseIds->implode(', ');
            $attributes = [
                'branch_id' => $branchIds->count() === 1 ? $branchIds->first() : null,
                'transaction_date' => $transactionDate,
                'source_type' => 'purchase',
                'source_reference' => substr($references, 0, 100),
            ];
            $company = $lockedPurchases->first()->company;

            $clearingEntry = $this->journalEntries->store(
                $company,
                [...$attributes, 'description' => 'Clear accounts payable for '.$lockedPurchases->count().' purchases'],
                $clearLines,
                $user,
                post: true,
            );

            $paymentEntry = $this->journalEntries->store(
                $company,
                [...$attributes, 'description' => 'Payment for '.$lockedPurchases->count().' purchases'],
                $paymentLines,
                $user,
                post: true,
            );

            foreach ($paymentData as $purchase) {
                $purchase->fill([
                    'payment_status' => 'paid',
                    'settlement_account' => $settlementAccount,
                    'bank_name' => $bankName,
                    'check_number' => $checkNumber,
                    'clearing_journal_entry_id' => $clearingEntry->id,
                    'payment_journal_entry_id' => $paymentEntry->id,
                    'paid_by' => $user?->id,
                    'paid_at' => now(),
                ])->save();
            }

            return $paymentEntry;
        });
    }

    public function void(Purchase $purchase, mixed $date = null, ?User $user = null): void
    {
        if ($purchase->status === 'voided') {
            throw ValidationException::withMessages([
                'status' => 'This purchase is already voided.',
            ]);
        }

        DB::transaction(function () use ($purchase, $date, $user): void {
            $voucher = \App\Models\IntercompanyVoucher::query()
                ->where('source_type', 'purchase')->where('source_id', $purchase->id)->first();
            if ($voucher?->paymentJournalEntry?->status === 'posted') {
                $this->journalEntries->reverse($voucher->paymentJournalEntry, $date ?? now(), $user);
            }
            foreach ([$purchase->journalEntry, $purchase->intercompanyJournalEntry] as $entry) {
                if ($entry?->status === 'posted') {
                    $this->journalEntries->reverse($entry, $date ?? now(), $user);
                }
            }

            $voucher?->update(['status' => 'cancelled']);

            $purchase->fill([
                'status' => 'voided',
                'voided_by' => $user?->id,
                'voided_at' => now(),
            ])->save();
        });
    }

    /**
     * @return list<array{account_id: int, debit: string, credit: string, description: string}>
     */
    public function lines(Purchase $purchase): array
    {
        $purchase->loadMissing('lines.account');

        $config = (array) config('accounting.purchases');

        if ($purchase->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one line item before posting.',
            ]);
        }

        $totalTaxable = 0;
        $totalVat = 0;
        $totalWithholding = 0;
        $accountDebits = [];

        foreach ($purchase->lines as $line) {
            $amount = Money::cents($line->amount);
            $vat = Money::cents($line->vat_amount);
            $withholding = Money::cents($line->withholding_tax_amount);

            $accountDebits[$line->account_id] = ($accountDebits[$line->account_id] ?? 0) + $amount;
            $totalTaxable += $amount;
            $totalVat += $vat;
            $totalWithholding += $withholding;
        }

        $gross = $totalTaxable + $totalVat;
        $netPayable = $gross - $totalWithholding;

        $payable = $this->payableAccount($purchase);
        $inputVat = $totalVat > 0
            ? $this->branchAccount($purchase, $config['input_vat_account'] ?? null)
            : null;
        $withholdingPayable = $totalWithholding > 0
            ? $this->branchAccount($purchase, $config['withholding_tax_payable_account'] ?? null)
            : null;

        $description = $purchase->vendorDisplayName() ?: 'Purchase';
        $journalLines = [];

        foreach ($accountDebits as $accountId => $cents) {
            $journalLines[] = $this->line($accountId, $cents, 0, $description);
        }

        if ($inputVat) {
            $journalLines[] = $this->line($inputVat->id, $totalVat, 0, 'Input VAT — ' . $description);
        }

        $journalLines[] = $this->line($payable->id, 0, $netPayable, $description);

        if ($withholdingPayable) {
            $journalLines[] = $this->line($withholdingPayable->id, 0, $totalWithholding, 'Withholding tax — ' . $description);
        }

        return $journalLines;
    }

    /**
     * Resolve a configured account (base code or per-branch map) for the purchase's branch.
     */
    protected function branchAccount(Purchase $purchase, string|array|null $config, string $errorKey = 'status'): Account
    {
        $purchase->loadMissing('branch');

        if (! $purchase->branch) {
            throw ValidationException::withMessages([
                $errorKey => 'This purchase has no branch assigned, so its accounts cannot be determined.',
            ]);
        }

        try {
            return BranchAccounts::account($config, $purchase->branch);
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }
    }

    protected function payableAccount(Purchase $purchase): Account
    {
        // Existing posted purchases predate the manual selection and retain
        // their original configured A/P account.
        if (! $purchase->payable_account_id && $purchase->status === 'posted') {
            return $this->branchAccount($purchase, config('accounting.purchases.payable_account'), 'payable_account_id');
        }

        if (! $purchase->payable_account_id) {
            throw ValidationException::withMessages([
                'payable_account_id' => 'Select a credit account before posting this purchase.',
            ]);
        }

        $account = $purchase->payableAccount;
        if (! $account || $account->company_id !== $purchase->company_id || ! $account->isPostable() || $account->accountType?->category !== 'liability') {
            throw ValidationException::withMessages([
                'payable_account_id' => 'Select an active liability account for this company.',
            ]);
        }

        $purchase->loadMissing('branch');
        if (! $purchase->branch || (str_contains($account->code, '-') && ! str_ends_with($account->code, '-'.$purchase->branch->accountSuffix()))) {
            throw ValidationException::withMessages([
                'payable_account_id' => 'Select a payable account for this purchase branch.',
            ]);
        }

        return $account;
    }

    protected function line(int $accountId, int $debitCents, int $creditCents, string $description): array
    {
        return [
            'account_id' => $accountId,
            'debit' => Money::decimal($debitCents),
            'credit' => Money::decimal($creditCents),
            'description' => $description,
        ];
    }



    public function sendForCheque(Purchase $purchase): void
    {
        $this->guardPending($purchase);

        // Fails early if the cheque account isn't configured for this branch.
        $this->branchAccount($purchase, config('accounting.purchases.settlement_accounts.cheque'), 'settlement_account');

        $purchase->fill([
            'payment_status' => 'for_check',
            'settlement_account' => 'cheque',
        ])->save();
    }

    public function setChequeDetails(Purchase $purchase, string $bankName, string $checkNumber): void
    {
        $this->guardForCheque($purchase);

        $purchase->fill([
            'bank_name' => $bankName,
            'check_number' => $checkNumber,
        ])->save();
    }

    public function cancelPayment(Purchase $purchase): void
    {
        if (
            $purchase->status !== 'posted'
            || ! in_array($purchase->payment_status, ['for_check', 'for_release'], true)
        ) {
            throw ValidationException::withMessages([
                'status' => 'This purchase is not awaiting release.',
            ]);
        }

        $purchase->fill([
            'payment_status' => 'unpaid', // use your existing default value for unpaid
            'settlement_account' => null,
            'bank_name' => null,
            'check_number' => null,
        ])->save();
    }

    public function release(Purchase $purchase, ?User $user = null, mixed $date = null): JournalEntry
    {
        if ($purchase->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => 'This purchase is not awaiting release.',
            ]);
        }

        if ($purchase->payment_status === 'for_check') {
            if (! $purchase->check_number) {
                throw ValidationException::withMessages([
                    'check_number' => 'Enter the cheque number before releasing.',
                ]);
            }

            return $this->pay($purchase, 'cheque', $user, $date, $purchase->bank_name, $purchase->check_number);
        }

        if ($purchase->payment_status === 'for_release' && $purchase->settlement_account && $purchase->settlement_account !== 'cheque') {
            return $this->pay($purchase, $purchase->settlement_account, $user, $date);
        }

        throw ValidationException::withMessages([
            'status' => 'This purchase is not awaiting release.',
        ]);
    }

    protected function guardPending(Purchase $purchase): void
    {
        if (! $purchase->canMarkPaid() || in_array($purchase->payment_status, ['paid', 'for_check', 'for_release'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This purchase is not awaiting payment.',
            ]);
        }
    }

    protected function guardForCheque(Purchase $purchase): void
    {
        if ($purchase->status !== 'posted' || $purchase->payment_status !== 'for_check') {
            throw ValidationException::withMessages([
                'status' => 'This purchase is not in the cheque process.',
            ]);
        }
    }
}
