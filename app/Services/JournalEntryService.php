<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PosEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates, posts and reverses double entry journal entries.
 *
 * The rules enforced here (and mirrored in the form requests) are:
 *  - every entry is filed into the accounting period that covers its date;
 *  - an entry only balances when total debits equal total credits;
 *  - only drafts can be edited or removed, and only drafts can be posted;
 *  - nothing can be posted into a closed period or a closed fiscal year;
 *  - a posted entry is never mutated, it is corrected with a reversing entry.
 */
class JournalEntryService
{
    public function __construct(
        protected FiscalYearService $fiscalYears,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function store(
        Company $company,
        array $attributes,
        array $lines,
        ?User $user = null,
        bool $post = false,
    ): JournalEntry {
        $transactionDate = $this->fiscalYears->date($attributes['transaction_date'])->toDateString();
        $period = $this->requirePeriod($company, $transactionDate);

        $reference = filled($attributes['reference_no'] ?? null)
            ? (string) $attributes['reference_no']
            : $this->nextReference($company, $transactionDate);

        return DB::transaction(function () use ($company, $attributes, $lines, $user, $post, $transactionDate, $period, $reference) {
            /** @var JournalEntry $entry */
            $entry = JournalEntry::query()->create([
                'company_id' => $company->id,
                'branch_id' => $attributes['branch_id'] ?? null,
                'fiscal_year_id' => $period->fiscal_year_id,
                'accounting_period_id' => $period->id,
                'reference_no' => $reference,
                'transaction_date' => $transactionDate,
                'description' => (string) $attributes['description'],
                'source_type' => $attributes['source_type'] ?? 'manual',
                'source_reference' => $attributes['source_reference'] ?? null,
                'status' => 'draft',
                'created_by' => $user?->id,
            ]);

            $this->replaceLines($entry, $lines);

            if ($post) {
                $this->post($entry, $user);
            }

            return $entry->fresh(['lines.account.accountType', 'accountingPeriod.fiscalYear', 'branch', 'posEntry'])
                ?? $entry;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(
        JournalEntry $entry,
        array $attributes,
        array $lines,
        ?User $user = null,
        bool $post = false,
    ): JournalEntry {
        $this->assertEditable($entry);

        $transactionDate = $this->fiscalYears->date($attributes['transaction_date'])->toDateString();
        $period = $this->requirePeriod($entry->company, $transactionDate);

        DB::transaction(function () use ($entry, $attributes, $lines, $user, $post, $transactionDate, $period): void {
            $entry->fill([
                'branch_id' => $attributes['branch_id'] ?? null,
                'fiscal_year_id' => $period->fiscal_year_id,
                'accounting_period_id' => $period->id,
                'transaction_date' => $transactionDate,
                'description' => (string) $attributes['description'],
            ])->save();

            $this->replaceLines($entry, $lines);

            if ($post) {
                $this->post($entry, $user);
            }
        });

        return $entry->fresh(['lines.account.accountType', 'accountingPeriod.fiscalYear', 'branch', 'posEntry'])
            ?? $entry;
    }

    public function post(JournalEntry $entry, ?User $user = null): JournalEntry
    {
        if ($entry->workbook_snapshot !== null) {
            throw ValidationException::withMessages(['status' => 'Workbook journals are review-only drafts. Resolve the calculation and account mappings before ledger posting is enabled.']);
        }
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft entries can be posted.',
            ]);
        }

        $this->assertBalanced($entry);

        /** @var AccountingPeriod|null $period */
        $period = $entry->accountingPeriod;

        if (! $period) {
            throw ValidationException::withMessages([
                'transaction_date' => 'This entry is no longer filed in an accounting period.',
            ]);
        }

        $this->fiscalYears->assertOpen($period);

        $entry->fill([
            'status' => 'posted',
            'posted_by' => $user?->id,
            'posted_at' => now(),
        ])->save();

        return $entry;
    }

    public function unpost(JournalEntry $entry): JournalEntry
    {
        if ($entry->source_type !== 'manual') {
            throw ValidationException::withMessages([
                'status' => 'POS-generated entries are voided from the POS entry screen, not unposted.',
            ]);
        }

        if ($entry->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Only a posted entry can be returned to draft.',
            ]);
        }

        $entry->fill([
            'status' => 'draft',
            'posted_by' => null,
            'posted_at' => null,
        ])->save();

        return $entry;
    }

    /**
     * Post a reversing entry (debits and credits swapped) and mark the
     * original as reversed. A POS summary linked to it is voided as well.
     */
    public function reverse(JournalEntry $entry, mixed $date = null, ?User $user = null): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw ValidationException::withMessages([
                'reversal' => 'Only posted entries can be reversed.',
            ]);
        }

        if ($entry->reversed_by_id) {
            throw ValidationException::withMessages([
                'reversal' => "{$entry->reference_no} has already been reversed.",
            ]);
        }

        $date = $this->fiscalYears->date($date ?? now())->toDateString();

        $period = $this->requirePeriod($entry->company, $date);
        $this->fiscalYears->assertOpen($period);

        $entry->loadMissing('lines');

        if ($entry->lines->count() < 2) {
            throw ValidationException::withMessages([
                'reversal' => " {$entry->reference_no} has fewer than two lines and cannot be reversed.",
            ]);
        }

        return DB::transaction(function () use ($entry, $period, $user, $date) {
            /** @var JournalEntry $reversal */
            $reversal = JournalEntry::query()->create([
                'company_id' => $entry->company_id,
                'branch_id' => $entry->branch_id,
                'fiscal_year_id' => $period->fiscal_year_id,
                'accounting_period_id' => $period->id,
                'reference_no' => $this->nextReference($entry->company, $date),
                'transaction_date' => $date,
                'description' => Str::limit("Reversal of {$entry->reference_no}: {$entry->description}", 255, ''),
                'source_type' => $entry->source_type,
                'source_reference' => $entry->source_reference,
                'status' => 'posted',
                'created_by' => $user?->id,
                'posted_by' => $user?->id,
                'posted_at' => now(),
                'reverses_id' => $entry->id,
            ]);

            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => $line->description,
                ]);
            }

            $this->assertBalanced($reversal);

            $entry->fill(['status' => 'reversed', 'reversed_by_id' => $reversal->id])->save();

            // The operational record and the ledger must not disagree: a
            // reversed day summary is a voided one.
            /** @var PosEntry|null $posEntry */
            $posEntry = $entry->posEntry;

            if ($posEntry && $posEntry->status !== 'voided') {
                $posEntry->fill([
                    'status' => 'voided',
                    'voided_by' => $user?->id,
                    'voided_at' => now(),
                ])->save();
            }

            return $reversal;
        });
    }

    public function delete(JournalEntry $entry): void
    {
        $this->assertEditable($entry);

        DB::transaction(function () use ($entry): void {
            $entry->lines()->delete();
            $entry->delete();
        });
    }

    public function assertEditable(JournalEntry $entry): void
    {
        if ($entry->source_type !== 'manual') {
            throw ValidationException::withMessages([
                'status' => 'This entry was generated automatically and can only be managed from where it was created.',
            ]);
        }

        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft entries can be changed. Reverse the entry and record a new one instead.',
            ]);
        }
    }

    /**
     * A journal entry may only be posted once it balances to the centavo.
     *
     * @throws ValidationException
     */
    public function assertBalanced(JournalEntry $entry): void
    {
        $entry->loadMissing('lines');

        $debits = 0;
        $credits = 0;

        foreach ($entry->lines as $line) {
            if ($line->account_id === null) {
                throw ValidationException::withMessages(['lines' => 'Resolve all unmapped draft accounts before posting.']);
            }
            $debits += Money::cents($line->debit);
            $credits += Money::cents($line->credit);
        }

        if ($debits === 0 || $credits === 0) {
            throw ValidationException::withMessages([
                'lines' => 'A journal entry needs at least one debit line and one credit line.',
            ]);
        }

        if ($debits !== $credits) {
            throw ValidationException::withMessages([
                'lines' => sprintf(
                    'Debits (%s) and credits (%s) are out by %s.',
                    Money::format($debits),
                    Money::format($credits),
                    Money::format(abs($debits - $credits)),
                ),
            ]);
        }
    }

    /**
     * Turn raw form input into validated, centavo-exact line payloads.
     *
     * Blank lines are dropped so the form can send placeholder rows.
     *
     * @param  array<int, mixed>  $lines
     * @return list<array{account_id: int, debit: string, credit: string, description: string|null}>
     */
    public function prepareLines(Company $company, array $lines): array
    {
        $maxLines = (int) config('accounting.journal_entries.max_lines', 50);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least two lines with an account and an amount.',
            ]);
        }

        if (count($lines) > $maxLines) {
            throw ValidationException::withMessages([
                'lines' => "A single journal entry can have at most {$maxLines} lines.",
            ]);
        }

        $prepared = [];

        foreach (array_values($lines) as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $debit = Money::cents($line['debit'] ?? null);
            $credit = Money::cents($line['credit'] ?? null);

            if ($debit === 0 && $credit === 0) {
                continue;
            }

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.debit" => 'Line '.($index + 1).' cannot carry both a debit and a credit.',
                ]);
            }

            $account = $this->findPostableAccount($company, $line['account_id'] ?? null, $index);

            $prepared[] = [
                'account_id' => $account->id,
                'debit' => Money::decimal($debit),
                'credit' => Money::decimal($credit),
                'description' => filled($line['description'] ?? null) ? (string) $line['description'] : null,
            ];
        }

        if ($prepared === []) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one line with an account and an amount.',
            ]);
        }

        return $prepared;
    }

    /**
     * @param  array<int, mixed>  $lines
     */
    public function replaceLines(JournalEntry $entry, array $lines): void
    {
        $prepared = $this->prepareLines($entry->company, $lines);

        $entry->lines()->delete();

        foreach ($prepared as $line) {
            $entry->lines()->create($line);
        }

        $entry->unsetRelation('lines');
    }

    public function savePosDraft(PosEntry $pos, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($pos, $user) {
            $pos = PosEntry::query()->lockForUpdate()->findOrFail($pos->id);
            if (! $pos->isDraft() || ($pos->journalEntry && $pos->journalEntry->status !== 'draft')) {
                throw ValidationException::withMessages(['status' => 'Only draft POS journals can be regenerated.']);
            }
            $snapshot = app(PosWorkbookService::class)->preview($pos);
            $date = $pos->business_date->toDateString();
            $period = $this->requirePeriod($pos->company, $date);
            $entry = $pos->journalEntry ?? new JournalEntry([
                'company_id' => $pos->company_id,
                'reference_no' => $this->nextReference($pos->company, $date),
                'created_by' => $user?->id,
            ]);
            $entry->fill([
                'branch_id' => $pos->branch_id, 'fiscal_year_id' => $period->fiscal_year_id,
                'accounting_period_id' => $period->id, 'transaction_date' => $date,
                'description' => 'POS invoice '.$pos->pos_reference,
                'source_type' => 'pos', 'source_reference' => $pos->pos_reference,
                'status' => 'draft', 'workbook_snapshot' => $snapshot,
            ])->save();
            $accounts = Account::query()->where('company_id', $pos->company_id)
                ->where('is_active', true)->whereDoesntHave('children')
                ->whereIn('code', array_filter(array_column($snapshot['rows'], 'code')))->get()->keyBy('code');
            $entry->lines()->delete();
            foreach ($snapshot['rows'] as $index => $row) {
                $cents = (int) round($row['amount'] ?? 0);
                if ($cents === 0) {
                    $snapshot['rows'][$index]['account_resolved'] = true;

                    continue;
                }
                $account = $accounts->get($row['code']);
                $snapshot['rows'][$index]['account_resolved'] = $account !== null;
                $debit = $row['side'] === 'debit' ? $cents : -$cents;
                $entry->lines()->create([
                    'account_id' => $account?->id,
                    'debit' => Money::decimal(max($debit, 0)),
                    'credit' => Money::decimal(max(-$debit, 0)),
                    'description' => $row['label'],
                ]);
            }
            $entry->update(['workbook_snapshot' => $snapshot]);
            $pos->update(['journal_entry_id' => $entry->id]);

            return $entry;
        });
    }

    /**
     * JE-2026-00042: sequential per company and calendar year.
     */
    public function nextReference(Company $company, string $date): string
    {
        $prefix = sprintf(
            '%s-%s-',
            (string) config('accounting.journal_entries.reference_prefix', 'JE'),
            substr($date, 0, 4),
        );

        $padding = (int) config('accounting.journal_entries.reference_padding', 5);

        $last = JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('reference_no', 'like', $prefix.'%')
            ->orderByDesc('reference_no')
            ->value('reference_no');

        $sequence = $last ? (int) substr((string) $last, strlen($prefix)) + 1 : 1;

        do {
            $candidate = $prefix.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);

            $taken = JournalEntry::query()
                ->where('company_id', $company->id)
                ->where('reference_no', $candidate)
                ->exists();

            $sequence++;
        } while ($taken);

        return $candidate;
    }

    protected function requirePeriod(Company $company, string $date): AccountingPeriod
    {
        $period = $this->fiscalYears->periodFor($company, $date);

        if (! $period) {
            throw ValidationException::withMessages([
                'transaction_date' => sprintf(
                    'There is no fiscal year or accounting period covering %s. Set one up under Fiscal years first.',
                    $date,
                ),
            ]);
        }

        return $period;
    }

    /**
     * Accounts that carry sub-accounts are summaries, and inactive or foreign
     * accounts never take postings, so both are refused here.
     *
     * @throws ValidationException
     */
    protected function findPostableAccount(Company $company, mixed $accountId, int $index): Account
    {
        if ($accountId === null || $accountId === '') {
            throw ValidationException::withMessages([
                "lines.{$index}.account_id" => 'Line '.($index + 1).' needs an account.',
            ]);
        }

        /** @var Account|null $account */
        $account = Account::query()
            ->withCount('children')
            ->where('company_id', $company->id)
            ->whereKey($accountId)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                "lines.{$index}.account_id" => 'Line '.($index + 1).' uses an account that does not belong to this company.',
            ]);
        }

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                "lines.{$index}.account_id" => 'Line '.($index + 1)." uses {$account->code}, which is inactive.",
            ]);
        }

        if ((int) $account->children_count > 0) {
            throw ValidationException::withMessages([
                "lines.{$index}.account_id" => 'Line '.($index + 1)." posts to {$account->code} ({$account->name}), a summary account. Choose one of its sub-accounts instead.",
            ]);
        }

        return $account;
    }
}
