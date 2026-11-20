<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\BranchAccounts;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpensePostingService
{
    public function __construct(
        protected JournalEntryService $journalEntries,
    ) {}

    public function computeLineVat(float $amount, string $taxType): float
    {
        $vatRate = (float) config('accounting.expenses.vat_rate', 0.12);

        return $taxType === 'vatable' ? round($amount * $vatRate, 2) : 0.0;
    }

    public function computeLineWithholding(float $amount, float $withholdingRate): float
    {
        return $withholdingRate > 0 ? round($amount * ($withholdingRate / 100), 2) : 0.0;
    }

    public function post(Expense $expense, ?User $user = null): JournalEntry
    {
        if ($expense->status === 'posted') {
            throw ValidationException::withMessages([
                'status' => 'This expense has already been posted.',
            ]);
        }

        $lines = $this->lines($expense);

        return DB::transaction(function () use ($expense, $lines, $user) {
            $entry = $this->journalEntries->store(
                $expense->company,
                [
                    'branch_id' => $expense->branch_id,
                    'transaction_date' => $expense->expense_date->toDateString(),
                    'description' => $expense->description
                        ?: ($expense->payee ? "Expense — {$expense->payee}" : 'Expense'),
                    'source_type' => 'expense',
                    'source_reference' => $expense->reference_no,
                ],
                $lines,
                $user,
                post: true,
            );

            $expense->fill([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
                'posted_by' => $user?->id,
                'posted_at' => now(),
            ])->save();

            return $entry;
        });
    }

    public function pay(
        Expense $expense,
        string $settlementAccount,
        ?User $user = null,
        mixed $date = null,
        ?string $bankName = null,
        ?string $checkNumber = null,
    ): JournalEntry {
        if (! $expense->canMarkPaid()) {
            throw ValidationException::withMessages([
                'status' => 'This expense is not awaiting payment.',
            ]);
        }

        $expense->loadMissing('lines');

        $config = (array) config('accounting.expenses');
        $settlementConfig = $config['settlement_accounts'][$settlementAccount] ?? null;

        if (! ($config['payable_account'] ?? null) || ! $settlementConfig) {
            throw ValidationException::withMessages([
                'settlement_account' => 'Choose a valid payment source.',
            ]);
        }

        $payable = $this->branchAccount($expense, $config['payable_account']);
        $settlement = $this->branchAccount($expense, $settlementConfig, 'settlement_account');

        $amount = Money::cents($expense->netPayable());
        $description = $expense->payee ? "Payment — {$expense->payee}" : 'Expense payment';
        $transactionDate = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        $lines = [
            $this->line($payable->id, $amount, 0, $description),
            $this->line($settlement->id, 0, $amount, $description),
        ];

        return DB::transaction(function () use ($expense, $lines, $user, $transactionDate, $settlementAccount, $description, $bankName, $checkNumber) {
            $entry = $this->journalEntries->store(
                $expense->company,
                [
                    'branch_id' => $expense->branch_id,
                    'transaction_date' => $transactionDate,
                    'description' => $description,
                    'source_type' => 'expense',
                    'source_reference' => $expense->reference_no,
                ],
                $lines,
                $user,
                post: true,
            );

            $expense->fill([
                'payment_status' => 'paid',
                'settlement_account' => $settlementAccount,
                'bank_name' => $bankName,
                'check_number' => $checkNumber,
                'payment_journal_entry_id' => $entry->id,
                'paid_by' => $user?->id,
                'paid_at' => now(),
            ])->save();

            return $entry;
        });
    }

    public function void(Expense $expense, mixed $date = null, ?User $user = null): void
    {
        if ($expense->status === 'voided') {
            throw ValidationException::withMessages([
                'status' => 'This expense is already voided.',
            ]);
        }

        DB::transaction(function () use ($expense, $date, $user): void {
            $voucher = \App\Models\IntercompanyVoucher::query()
                ->where('source_type', 'expense')->where('source_id', $expense->id)->first();
            if ($voucher?->paymentJournalEntry?->status === 'posted') {
                $this->journalEntries->reverse($voucher->paymentJournalEntry, $date ?? now(), $user);
            }
            foreach ([$expense->journalEntry, $expense->intercompanyJournalEntry] as $entry) {
                if ($entry?->status === 'posted') {
                    $this->journalEntries->reverse($entry, $date ?? now(), $user);
                }
            }

            $voucher?->update(['status' => 'cancelled']);

            $expense->fill([
                'status' => 'voided',
                'voided_by' => $user?->id,
                'voided_at' => now(),
            ])->save();
        });
    }

    /**
     * @return list<array{account_id: int, debit: string, credit: string, description: string}>
     */
    public function lines(Expense $expense): array
    {
        $expense->loadMissing('lines.account');

        $config = (array) config('accounting.expenses');

        if ($expense->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one line item before posting.',
            ]);
        }

        $totalTaxable = 0;
        $totalVat = 0;
        $totalWithholding = 0;
        $accountDebits = [];

        foreach ($expense->lines as $line) {
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

        $payable = $this->branchAccount($expense, $config['payable_account'] ?? null);
        $inputVat = $totalVat > 0
            ? $this->branchAccount($expense, $config['input_vat_account'] ?? null)
            : null;
        $withholdingPayable = $totalWithholding > 0
            ? $this->branchAccount($expense, $config['withholding_tax_payable_account'] ?? null)
            : null;

        $description = $expense->payee ?: 'Expense';
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
     * Resolve a configured account (base code or per-branch map) for the expense's branch.
     */
    protected function branchAccount(Expense $expense, string|array|null $config, string $errorKey = 'status'): Account
    {
        $expense->loadMissing('branch');

        if (! $expense->branch) {
            throw ValidationException::withMessages([
                $errorKey => 'This expense has no branch assigned, so its accounts cannot be determined.',
            ]);
        }

        try {
            return BranchAccounts::account($config, $expense->branch);
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }
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
}
