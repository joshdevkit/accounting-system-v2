<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\IntercompanyVoucher;
use App\Models\Purchase;
use App\Models\User;
use App\Support\BranchAccounts;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IntercompanyVoucherService
{
    public function __construct(private JournalEntryService $journals) {}

    public function markForVoucher(Purchase|Expense $source, ?User $user): IntercompanyVoucher
    {
        return DB::transaction(function () use ($source, $user): IntercompanyVoucher {
            $source = $source::query()->lockForUpdate()->findOrFail($source->id);
            $type = $source instanceof Purchase ? 'purchase' : 'expense';
            $existing = IntercompanyVoucher::query()
                ->where('source_type', $type)->where('source_id', $source->id)->lockForUpdate()->first();

            if ($source->status !== 'posted' || $source->payment_status !== 'unpaid' || $existing?->status === 'paid') {
                throw ValidationException::withMessages(['voucher' => 'This record is not awaiting a voucher.']);
            }

            $entry = $source->intercompanyJournalEntry;
            if (! $entry || $entry->status !== 'posted' || ! $entry->branch) {
                throw ValidationException::withMessages(['voucher' => 'Post the intercompany clearing entry with a branch first.']);
            }

            try {
                $clearing = BranchAccounts::account(config('accounting.purchases.clearing_account'), $entry->branch);
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['voucher' => $exception->getMessage()]);
            }
            $entry->loadMissing('lines');
            $credits = $entry->lines->filter(fn ($line) => Money::cents($line->credit) > 0);
            $amount = Money::cents($credits->first()?->credit);
            $sourceAmount = Money::cents($source->netPayable());
            $debits = $entry->lines->sum(fn ($line) => Money::cents($line->debit));

            if ($amount <= 0 || $amount !== $sourceAmount || $credits->count() !== 1
                || (int) $credits->first()->account_id !== (int) $clearing->id
                || $debits !== $amount) {
                throw ValidationException::withMessages([
                    'voucher' => 'The linked entry must debit the source amount and credit the paying branch A/P Clearing for the same amount.',
                ]);
            }

            $attributes = [
                'company_id' => $source->company_id,
                'source_type' => $type,
                'source_id' => $source->id,
                'intercompany_journal_entry_id' => $entry->id,
                'branch_id' => $entry->branch_id,
                'clearing_account_id' => $clearing->id,
                'payee' => $source instanceof Purchase ? ($source->vendorDisplayName() ?? '') : ($source->payee ?? ''),
                'source_reference' => $source->reference_no,
                'amount' => Money::decimal($amount),
                'status' => 'for_voucher',
                'created_by' => $user?->id,
            ];

            if (blank($attributes['payee'])) {
                throw ValidationException::withMessages(['voucher' => 'Enter a vendor or payee before marking this entry For Voucher.']);
            }

            if ($existing) {
                if ($existing->status !== 'cancelled') {
                    throw ValidationException::withMessages(['voucher' => 'This entry is already For Voucher.']);
                }
                $existing->fill($attributes)->save();

                return $existing;
            }

            return IntercompanyVoucher::query()->create($attributes);
        });
    }

    public function sendForCheque(IntercompanyVoucher $voucher): void
    {
        if ($voucher->status !== 'for_voucher') {
            throw ValidationException::withMessages([
                'voucher_ids' => 'Only vouchers in For Voucher status can be sent for cheque.',
            ]);
        }

        $voucher->update(['status' => 'send_for_cheque']);
    }

    /** @param list<IntercompanyVoucher> $vouchers */
    public function sendForChequeBatch(array $vouchers): void
    {
        if ($vouchers === []) {
            throw ValidationException::withMessages(['voucher_ids' => 'Select at least one intercompany voucher.']);
        }

        DB::transaction(function () use ($vouchers): void {
            $ids = collect($vouchers)->pluck('id')->unique()->values();
            $locked = IntercompanyVoucher::query()
                ->whereKey($ids)->lockForUpdate()->get();

            if ($locked->count() !== $ids->count()) {
                throw ValidationException::withMessages(['voucher_ids' => 'Some intercompany vouchers could not be found.']);
            }

            foreach ($locked as $voucher) {
                if ($voucher->status !== 'for_voucher') {
                    throw ValidationException::withMessages(['voucher_ids' => "Voucher #{$voucher->id} is no longer ready for cheque."]);
                }
                $voucher->update(['status' => 'send_for_cheque']);
            }
        });
    }

    public function markBatchForRelease(array $vouchers, string $settlementAccount): void
    {
        if ($vouchers === []) {
            throw ValidationException::withMessages(['voucher_ids' => 'Select at least one intercompany voucher.']);
        }

        if ($settlementAccount !== 'cheque' && !array_key_exists($settlementAccount, config('accounting.purchases.settlement_accounts') ?? [])) {
            throw ValidationException::withMessages(['settlement_account' => 'Choose a valid payment source.']);
        }

        DB::transaction(function () use ($vouchers, $settlementAccount): void {
            $ids = collect($vouchers)->pluck('id')->unique()->values();
            $locked = IntercompanyVoucher::query()
                ->whereKey($ids)->lockForUpdate()->get();

            if ($locked->count() !== $ids->count()) {
                throw ValidationException::withMessages(['voucher_ids' => 'Some intercompany vouchers could not be found.']);
            }

            foreach ($locked as $voucher) {
                if ($voucher->status !== 'for_voucher') {
                    throw ValidationException::withMessages(['voucher_ids' => "Voucher #{$voucher->id} is no longer ready for release."]);
                }
                $voucher->update([
                    'status' => 'for_release',
                    'settlement_account' => $settlementAccount,
                ]);
            }
        });
    }

    public function setChequeDetails(IntercompanyVoucher $voucher, string $bankName, string $checkNumber): void
    {
        if ($voucher->status !== 'send_for_cheque') {
            throw ValidationException::withMessages(['voucher_ids' => 'This voucher is not awaiting cheque details.']);
        }

        $voucher->update([
            'status' => 'for_release',
            'bank_name' => $bankName,
            'check_number' => $checkNumber,
        ]);
    }

    public function release(IntercompanyVoucher $voucher, ?User $user, ?string $paymentDate = null): void
    {
        if ($voucher->status !== 'for_release') {
            throw ValidationException::withMessages(['voucher_ids' => 'This voucher is not awaiting release.']);
        }

        // Same as purchases: a cheque release needs its number, every other
        // source releases straight from the settlement account it was marked
        // For Release with.
        $settlementKey = $voucher->settlement_account ?: 'cheque';
        if ($settlementKey === 'cheque' && ! $voucher->check_number) {
            throw ValidationException::withMessages(['voucher_ids' => 'Enter the cheque number before releasing.']);
        }

        $mapping = config("accounting.purchases.settlement_accounts.{$settlementKey}");
        if (! $mapping) {
            throw ValidationException::withMessages(['voucher_ids' => 'Choose a valid payment source.']);
        }

        DB::transaction(function () use ($voucher, $user, $paymentDate, $mapping, $settlementKey): void {
            $source = $voucher->source_type === 'purchase'
                ? Purchase::query()->lockForUpdate()->findOrFail($voucher->source_id)
                : Expense::query()->lockForUpdate()->findOrFail($voucher->source_id);

            if ($voucher->status !== 'for_release' || $source->status !== 'posted' || $source->payment_status !== 'unpaid') {
                throw ValidationException::withMessages(['voucher_ids' => "Voucher #{$voucher->id} is no longer ready for release."]);
            }

            try {
                $settlement = BranchAccounts::account($mapping, $voucher->branch);
            } catch (DomainException $exception) {
                throw ValidationException::withMessages(['voucher_ids' => $exception->getMessage()]);
            }

            $amount = Money::cents($voucher->amount);

            $lines = [
                ['account_id' => $voucher->clearing_account_id, 'debit' => Money::decimal($amount), 'credit' => '0.00', 'description' => 'Intercompany A/P Clearing'],
                ['account_id' => $settlement->id, 'debit' => '0.00', 'credit' => Money::decimal($amount), 'description' => $settlementKey === 'cheque' ? 'Intercompany payment - cheque settlement' : 'Intercompany payment settlement'],
            ];

            $company = $voucher->company;
            $entry = $this->journals->store($company, [
                'branch_id' => $voucher->branch_id,
                'transaction_date' => $paymentDate ?: now()->toDateString(),
                'description' => 'Intercompany payment for '.($voucher->source_reference ?: ucfirst($voucher->source_type).' #'.$voucher->source_id),
                'source_type' => 'manual',
                'source_reference' => $settlementKey === 'cheque'
                    ? 'Intercompany cheque: '.$voucher->check_number
                    : 'Intercompany vouchers: '.$voucher->id,
            ], $lines, $user, post: true);

            $voucher->update([
                'status' => 'paid', 'settlement_account' => $settlementKey,
                'payment_journal_entry_id' => $entry->id, 'paid_by' => $user?->id, 'paid_at' => now(),
            ]);
            $source->update([
                'payment_status' => 'paid', 'settlement_account' => $settlementKey,
                'payment_journal_entry_id' => $entry->id, 'paid_by' => $user?->id, 'paid_at' => now(),
            ]);
        });
    }

    public function cancelPayment(IntercompanyVoucher $voucher): void
    {
        if (! in_array($voucher->status, ['send_for_cheque', 'for_release'], true)) {
            throw ValidationException::withMessages(['voucher_ids' => 'This voucher is not awaiting payment cancellation.']);
        }

        $voucher->update([
            'status' => 'for_voucher',
            'bank_name' => null,
            'check_number' => null,
        ]);
    }

    public function pay(
        IntercompanyVoucher $voucher,
        string $settlementAccount,
        ?User $user,
        ?string $paymentDate = null,
        ?string $bankName = null,
        ?string $checkNumber = null,
    ): IntercompanyVoucher {
        $this->payBatch([$voucher], $settlementAccount, $user, $paymentDate, $bankName, $checkNumber);

        return $voucher->fresh() ?? $voucher;
    }

    /** @param list<IntercompanyVoucher> $vouchers */
    public function payBatch(array $vouchers, string $settlementAccount, ?User $user, ?string $paymentDate = null, ?string $bankName = null, ?string $checkNumber = null): void
    {
        if ($vouchers === []) {
            throw ValidationException::withMessages(['voucher_ids' => 'Select at least one intercompany voucher.']);
        }

        // Cheque uses same workflow as purchases: send for cheque first, then release later
        if ($settlementAccount === 'cheque') {
            $this->sendForChequeBatch($vouchers);
            return;
        }

        $mapping = config("accounting.purchases.settlement_accounts.{$settlementAccount}");
        if (! $mapping) {
            throw ValidationException::withMessages(['settlement_account' => 'Choose a valid payment source.']);
        }

        DB::transaction(function () use ($vouchers, $settlementAccount, $user, $paymentDate, $bankName, $checkNumber, $mapping): void {
            $ids = collect($vouchers)->pluck('id')->unique()->values();
            $locked = IntercompanyVoucher::query()->with(['branch', 'intercompanyJournalEntry', 'company'])
                ->whereKey($ids)->lockForUpdate()->get();
            if ($locked->count() !== $ids->count()) {
                throw ValidationException::withMessages(['voucher_ids' => 'Some intercompany vouchers could not be found.']);
            }

            $company = $locked->first()->company;
            $debits = [];
            $credits = [];
            $sources = [];

            foreach ($locked as $voucher) {
                $source = $voucher->source_type === 'purchase'
                    ? Purchase::query()->lockForUpdate()->findOrFail($voucher->source_id)
                    : Expense::query()->lockForUpdate()->findOrFail($voucher->source_id);
                if ($voucher->status !== 'for_voucher' || $source->status !== 'posted'
                    || $source->payment_status !== 'unpaid'
                    || $source->intercompany_journal_entry_id !== $voucher->intercompany_journal_entry_id
                    || $voucher->intercompanyJournalEntry?->status !== 'posted') {
                    throw ValidationException::withMessages(['voucher_ids' => "Voucher #{$voucher->id} is no longer ready for payment."]);
                }
                try {
                    $settlement = BranchAccounts::account($mapping, $voucher->branch);
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(['settlement_account' => $exception->getMessage()]);
                }
                $amount = Money::cents($voucher->amount);
                $debits[$voucher->clearing_account_id] = ($debits[$voucher->clearing_account_id] ?? 0) + $amount;
                $credits[$settlement->id] = ($credits[$settlement->id] ?? 0) + $amount;
                $sources[] = [$voucher, $source];
            }

            $lines = [];
            foreach ($debits as $accountId => $amount) {
                $lines[] = ['account_id' => $accountId, 'debit' => Money::decimal($amount), 'credit' => '0.00', 'description' => 'Intercompany A/P Clearing'];
            }
            foreach ($credits as $accountId => $amount) {
                $lines[] = ['account_id' => $accountId, 'debit' => '0.00', 'credit' => Money::decimal($amount), 'description' => 'Intercompany payment settlement'];
            }
            $branchIds = $locked->pluck('branch_id')->unique();
            $entry = $this->journals->store($company, [
                'branch_id' => $branchIds->count() === 1 ? $branchIds->first() : null,
                'transaction_date' => $paymentDate ?: now()->toDateString(),
                'description' => 'Intercompany payment for '.$locked->count().' voucher(s)',
                'source_type' => 'manual',
                'source_reference' => 'Intercompany vouchers: '.$ids->implode(', '),
            ], $lines, $user, post: true);

            foreach ($sources as [$voucher, $source]) {
                $voucher->update([
                    'status' => 'paid', 'settlement_account' => $settlementAccount,
                    'bank_name' => $bankName, 'check_number' => $checkNumber,
                    'payment_journal_entry_id' => $entry->id, 'paid_by' => $user?->id, 'paid_at' => now(),
                ]);
                $source->update([
                    'payment_status' => 'paid', 'settlement_account' => $settlementAccount,
                    'bank_name' => $bankName, 'check_number' => $checkNumber,
                    'payment_journal_entry_id' => $entry->id, 'paid_by' => $user?->id, 'paid_at' => now(),
                ]);
            }
        });
    }
}
