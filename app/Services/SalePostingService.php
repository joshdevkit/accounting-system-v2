<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a recorded sale into a balanced journal entry.
 *
 * A sale always accrues into Accounts Receivable first — collecting it later
 * is a separate action (receive()).
 *
 *   Dr  Accounts Receivable (net of any withholding the customer applies)
 *   Dr  Withholding Tax Receivable (only if the customer withheld tax)
 *       Cr  the chosen revenue account                    taxable amount
 *       Cr  Output VAT (only if the sale is VATable)        vat amount
 */
class SalePostingService
{
    public function __construct(
        protected JournalEntryService $journalEntries,
    ) {}

    /**
     * @return array{vat_amount: float, withholding_tax_amount: float}
     */
    public function computeTaxes(float $taxableAmount, string $taxType, float $withholdingRate): array
    {
        $vatRate = (float) config('accounting.sales.vat_rate', 0.12);

        $vatAmount = $taxType === 'vatable'
            ? round($taxableAmount * $vatRate, 2)
            : 0.0;

        $withholdingAmount = $withholdingRate > 0
            ? round($taxableAmount * ($withholdingRate / 100), 2)
            : 0.0;

        return [
            'vat_amount' => $vatAmount,
            'withholding_tax_amount' => $withholdingAmount,
        ];
    }

    public function post(Sale $sale, ?User $user = null): JournalEntry
    {
        if ($sale->status === 'posted') {
            throw ValidationException::withMessages([
                'status' => 'This sale has already been posted.',
            ]);
        }

        $lines = $this->lines($sale);

        return DB::transaction(function () use ($sale, $lines, $user) {
            $entry = $this->journalEntries->store(
                $sale->company,
                [
                    'branch_id' => $sale->branch_id,
                    'transaction_date' => $sale->sale_date->toDateString(),
                    'description' => $sale->customerDisplayName()
                        ? "Sale — {$sale->customerDisplayName()}"
                        : 'Sale',
                    'source_type' => 'sale',
                    'source_reference' => $sale->reference_no,
                ],
                $lines,
                $user,
                post: true,
            );

            $sale->fill([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
                'posted_by' => $user?->id,
                'posted_at' => now(),
            ])->save();

            return $entry;
        });
    }

    public function receive(Sale $sale, string $settlementAccount, ?User $user = null, mixed $date = null): JournalEntry
    {
        if ($sale->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Only a posted sale can be marked as paid.',
            ]);
        }

        if ($sale->payment_status !== 'unpaid') {
            throw ValidationException::withMessages([
                'status' => 'This sale is not marked unpaid.',
            ]);
        }

        $config = (array) config('accounting.sales');
        $receivableCode = $config['receivable_account'] ?? null;
        $settlementCode = $config['settlement_accounts'][$settlementAccount] ?? null;

        if (! $receivableCode || ! $settlementCode) {
            throw ValidationException::withMessages([
                'settlement_account' => 'Choose a valid receiving account.',
            ]);
        }

        $accounts = Account::query()
            ->where('company_id', $sale->company_id)
            ->whereIn('code', [$receivableCode, $settlementCode])
            ->get()
            ->keyBy('code');

        $missing = array_values(array_filter(
            [$receivableCode, $settlementCode],
            fn(string $code): bool => ! $accounts->has($code),
        ));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'status' => 'The chart of accounts is missing ' . implode(', ', $missing) . '.',
            ]);
        }

        $amount = Money::cents($sale->netReceivable());
        $description = $sale->customer_name ? "Payment received — {$sale->customer_name}" : 'Sale payment received';
        $transactionDate = $date ? \Carbon\Carbon::parse($date)->toDateString() : now()->toDateString();

        $lines = [
            $this->line($accounts->get($settlementCode)->id, $amount, 0, $description),
            $this->line($accounts->get($receivableCode)->id, 0, $amount, $description),
        ];

        return DB::transaction(function () use ($sale, $lines, $user, $transactionDate, $settlementAccount, $description) {
            $entry = $this->journalEntries->store(
                $sale->company,
                [
                    'branch_id' => $sale->branch_id,
                    'transaction_date' => $transactionDate,
                    'description' => $description,
                    'source_type' => 'sale',
                    'source_reference' => $sale->reference_no,
                ],
                $lines,
                $user,
                post: true,
            );

            $sale->fill([
                'payment_status' => 'paid',
                'settlement_account' => $settlementAccount,
                'payment_journal_entry_id' => $entry->id,
                'paid_by' => $user?->id,
                'paid_at' => now(),
            ])->save();

            return $entry;
        });
    }

    public function void(Sale $sale, mixed $date = null, ?User $user = null): void
    {
        if ($sale->status === 'voided') {
            throw ValidationException::withMessages([
                'status' => 'This sale is already voided.',
            ]);
        }

        $entry = $sale->journalEntry;

        if ($entry && $entry->status === 'posted') {
            $this->journalEntries->reverse($entry, $date ?? now(), $user);
        }

        $sale->fill([
            'status' => 'voided',
            'voided_by' => $user?->id,
            'voided_at' => now(),
        ])->save();
    }

    /**
     * @return list<array{account_id: int, debit: string, credit: string, description: string}>
     */
    public function lines(Sale $sale): array
    {
        $config = (array) config('accounting.sales');

        $taxable = Money::cents($sale->taxable_amount);
        $vat = Money::cents($sale->vat_amount);
        $withholding = Money::cents($sale->withholding_tax_amount);

        if ($taxable === 0) {
            throw ValidationException::withMessages([
                'taxable_amount' => 'The sale amount cannot be zero.',
            ]);
        }

        $gross = $taxable + $vat;
        $netReceivable = $gross - $withholding;

        $codes = array_values(array_unique(array_filter([
            $config['receivable_account'] ?? null,
            $vat > 0 ? ($config['output_vat_account'] ?? null) : null,
            $withholding > 0 ? ($config['withholding_tax_receivable_account'] ?? null) : null,
        ])));

        $accounts = Account::query()
            ->where('company_id', $sale->company_id)
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        $missing = array_values(array_filter($codes, fn(string $code): bool => ! $accounts->has($code)));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'status' => 'The chart of accounts is missing ' . implode(', ', $missing) . ', which this sale needs before it can be posted.',
            ]);
        }

        $description = $sale->customer_name ?: 'Sale';
        $lines = [];

        // Dr Accounts Receivable for the net amount the customer actually owes.
        $lines[] = $this->line($accounts->get($config['receivable_account'])->id, $netReceivable, 0, $description);

        // Dr Withholding Tax Receivable, if the customer withheld tax.
        if ($withholding > 0) {
            $lines[] = $this->line($accounts->get($config['withholding_tax_receivable_account'])->id, $withholding, 0, 'Withholding tax — ' . $description);
        }

        // Cr the chosen revenue account for the taxable (VAT-exclusive) amount.
        $lines[] = $this->line($sale->credit_account_id, 0, $taxable, $description);

        // Cr Output VAT, if VATable.
        if ($vat > 0) {
            $lines[] = $this->line($accounts->get($config['output_vat_account'])->id, 0, $vat, 'Output VAT — ' . $description);
        }

        return $lines;
    }

    /**
     * @return array{account_id: int, debit: string, credit: string, description: string}
     */
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
