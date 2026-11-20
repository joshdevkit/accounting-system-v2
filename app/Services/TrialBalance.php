<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntryLine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Balances derived from posted journal entry lines.
 *
 * Summary (parent) accounts are never posted to directly, so their balance is
 * the roll-up of their sub-accounts. Amounts travel to the browser as integer
 * centavos, which keeps the arithmetic exact and formatting a view concern.
 */
class TrialBalance
{
    /**
     * @param  array{branch_id?: int|null, fiscal_year_id?: int|null, accounting_period_id?: int|null, from?: string|null, to?: string|null, statuses?: list<string>}  $filters
     * @return list<array<string, mixed>>
     */
    public function rows(Company $company, array $filters = [], bool $includeSummaryAccounts = true): array
    {
        $accounts = $this->accounts($company);
        $aggregate = $this->aggregate($company, $filters);
        $rolled = $this->rollUp($accounts, $aggregate);

        $rows = [];

        foreach ($accounts as $account) {
            $isSummary = $account->hasChildren();

            if ($isSummary && ! $includeSummaryAccounts) {
                continue;
            }

            $own = $aggregate[$account->id] ?? ['debit' => 0, 'credit' => 0];
            $total = $rolled[$account->id] ?? ['debit' => 0, 'credit' => 0];

            $rows[] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'category' => $account->accountType?->category,
                'normal_balance' => $this->expectedDirection($account),
                'depth' => count($this->ancestors($accounts, $account)),
                'is_summary' => $isSummary,
                'is_postable' => $account->isPostable(),
                'own_debit' => $own['debit'],
                'own_credit' => $own['credit'],
                'debit' => $total['debit'],
                'credit' => $total['credit'],
                'balance' => $this->signed($total['debit'] - $total['credit'], $account),
            ];
        }

        return $rows;
    }

    /**
     * Grand totals for the trial balance.
     *
     * Summary accounts are excluded from the totals because their balances are
     * roll-ups of their postable child accounts. Each postable account contributes
     * only its ending debit or credit balance.
     *
     * @param  array<string, mixed>  $filters
     * @return array{debits: int, credits: int, difference: int}
     */
    public function totals(Company $company, array $filters = []): array
    {
        $accounts = $this->accounts($company);
        $aggregate = $this->aggregate($company, $filters);

        $debits = 0;
        $credits = 0;

        foreach ($accounts as $account) {
            if ($account->hasChildren()) {
                continue;
            }

            $amounts = $aggregate[$account->id] ?? [
                'debit' => 0,
                'credit' => 0,
            ];

            $balance = $amounts['debit'] - $amounts['credit'];

            if ($balance > 0) {
                $debits += $balance;
            } elseif ($balance < 0) {
                $credits += abs($balance);
            }
        }

        return [
            'debits' => $debits,
            'credits' => $credits,
            'difference' => $debits - $credits,
        ];
    }

    /**
     * Signed balance (in each account's normal direction) for a set of account
     * codes, including their sub-accounts. Used for dashboard KPIs.
     *
     * @param  list<string>  $codes
     * @param  array<string, mixed>  $filters
     */
    public function balanceCentsForCodes(Company $company, array $codes, array $filters = []): int
    {
        if ($codes === []) {
            return 0;
        }

        $accounts = $this->accounts($company);

        $wanted = [];

        foreach ($accounts as $account) {
            if (! in_array($account->code, $codes, true)) {
                continue;
            }

            foreach ($this->subtree($accounts, $account) as $id) {
                $wanted[$id] = true;
            }
        }

        if ($wanted === []) {
            return 0;
        }

        $aggregate = $this->aggregate($company, $filters);
        $balance = 0;

        foreach ($accounts as $account) {
            if (! isset($wanted[$account->id])) {
                continue;
            }

            $amounts = $aggregate[$account->id] ?? null;

            if ($amounts) {
                $balance += $this->signed($amounts['debit'] - $amounts['credit'], $account);
            }
        }

        return $balance;
    }

    /**
     * Account statement with a running balance.
     *
     * @param  array<string, mixed>  $filters
     * @return array{lines: list<array<string, mixed>>, debit_cents: int, credit_cents: int, balance_cents: int}
     */
    public function ledger(Company $company, Account $account, array $filters = []): array
    {
        $accounts = $this->accounts($company);

        $ids = ! empty($filters['include_sub_accounts'])
            ? $this->subtree($accounts, $account)
            : [$account->id];

        /** @var Collection<int, JournalEntryLine> $lines */
        $lines = JournalEntryLine::query()
            ->with([
                'account',
                'journalEntry' => fn(Builder|Relation $query) => $query
                    ->with('branch:id,name')
                    ->with('contact:id,name') // 👈 1. eager load the contact
                    ->with([
                        'purchase.contact:id,name',
                        'paymentPurchase.contact:id,name',
                        'clearingPurchase.contact:id,name',
                    ])
                    ->with('accountingPeriod:id,name'),
            ])
            ->whereIn('account_id', $ids)
            ->whereHas('journalEntry', function (Builder $query) use ($company, $filters): void {
                $this->applyEntryFilters($query, $company, $filters);
            })
            ->get();

        $ordered = $lines->sortBy(fn(JournalEntryLine $line): array => [
            $line->journalEntry?->transaction_date?->getTimestamp() ?? 0,
            $line->journal_entry_id,
            $line->id,
        ])->values();

        $direction = $this->expectedDirection($account);
        $running = 0;
        $debitCents = 0;
        $creditCents = 0;
        $rows = [];

        foreach ($ordered as $line) {
            $debit = Money::cents($line->debit);
            $credit = Money::cents($line->credit);

            $debitCents += $debit;
            $creditCents += $credit;

            $running += $direction === 'credit' ? $credit - $debit : $debit - $credit;

            $rows[] = [
                'id' => $line->id,
                'journal_entry_id' => $line->journal_entry_id,
                'date' => $line->journalEntry?->transaction_date?->toDateString(),
                'reference_no' => $line->journalEntry?->reference_no,
                'status' => $line->journalEntry?->status,
                'source_type' => $line->journalEntry?->source_type,
                'branch' => $line->journalEntry?->branch?->name,
                'vendor' => $line->journalEntry?->contact?->name
                    ?? $line->journalEntry?->purchase?->contact?->name
                    ?? $line->journalEntry?->paymentPurchase?->contact?->name
                    ?? $line->journalEntry?->clearingPurchase?->contact?->name,
                'entry_description' => $line->journalEntry?->description,
                'description' => $line->description,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
            ];
        }

        return [
            'lines' => $rows,
            'debit_cents' => $debitCents,
            'credit_cents' => $creditCents,
            'balance_cents' => $running,
        ];
    }
    /**
     * @param  Builder<JournalEntryLine>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyEntryFilters(Builder $query, Company $company, array $filters): void
    {
        $query->where('journal_entries.company_id', $company->id)
            ->whereIn('journal_entries.status', $filters['statuses'] ?? ['posted']);

        foreach (['branch_id', 'fiscal_year_id', 'accounting_period_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where("journal_entries.{$key}", $filters[$key]);
            }
        }

        if (! empty($filters['from'])) {
            $query->whereDate('journal_entries.transaction_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('journal_entries.transaction_date', '<=', $filters['to']);
        }
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  array<int, array{debit: int, credit: int}>  $aggregate
     * @return array<int, array{debit: int, credit: int}>
     */
    protected function rollUp(Collection $accounts, array $aggregate): array
    {
        $rolled = $aggregate;

        foreach ($accounts as $account) {
            if (empty($aggregate[$account->id])) {
                continue;
            }

            foreach ($this->ancestors($accounts, $account) as $ancestorId) {
                $rolled[$ancestorId]['debit'] = ($rolled[$ancestorId]['debit'] ?? 0) + $aggregate[$account->id]['debit'];
                $rolled[$ancestorId]['credit'] = ($rolled[$ancestorId]['credit'] ?? 0) + $aggregate[$account->id]['credit'];
            }
        }

        return $rolled;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{debit: int, credit: int}>
     */
    protected function aggregate(Company $company, array $filters = []): array
    {
        $unit = $filters['unit'] ?? null;

        $records = JournalEntryLine::query()
            ->selectRaw(
                'account_id, COALESCE(SUM(debit), 0) as summed_debit, COALESCE(SUM(credit), 0) as summed_credit'
            )
            ->whereHas('journalEntry', function (Builder $query) use ($company, $filters): void {
                $this->applyEntryFilters($query, $company, $filters);
            })
            ->when(
                in_array($unit, ['maxi', 'gusto'], true),
                function (Builder $query) use ($unit): void {
                    $suffix = $unit === 'gusto' ? '-02' : '-01';

                    $query->whereHas('account', function (Builder $accountQuery) use ($suffix): void {
                        $accountQuery->where('code', 'like', "%{$suffix}");
                    });
                }
            )
            ->groupBy('account_id')
            ->get();

        $aggregate = [];

        foreach ($records as $record) {
            $aggregate[(int) $record->account_id] = [
                'debit' => Money::cents($record->summed_debit),
                'credit' => Money::cents($record->summed_credit),
            ];
        }

        return $aggregate;
    }

    /**
     * @return Collection<int, Account>
     */
    protected function accounts(Company $company): Collection
    {
        /** @var Collection<int, Account> $accounts */
        return $company->accounts()
            ->with('accountType')
            ->withCount('children')
            ->orderBy('code')
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return list<int>
     */
    protected function ancestors(Collection $accounts, Account $account): array
    {
        $chain = [];
        $parentId = $account->parent_id;
        $guard = 0;

        while ($parentId !== null && isset($accounts[$parentId]) && $guard++ < 20) {
            $chain[] = $parentId;
            $parentId = $accounts[$parentId]->parent_id;
        }

        return $chain;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return list<int>
     */
    protected function subtree(Collection $accounts, Account $account): array
    {
        $ids = [$account->id];
        $frontier = [$account->id];
        $guard = 0;

        while ($frontier !== [] && $guard++ < 20) {
            $next = [];

            foreach ($accounts as $candidate) {
                if (
                    $candidate->parent_id !== null
                    && in_array($candidate->parent_id, $frontier, true)
                    && ! in_array($candidate->id, $ids, true)
                ) {
                    $ids[] = $candidate->id;
                    $next[] = $candidate->id;
                }
            }

            $frontier = $next;
        }

        return $ids;
    }

    /**
     * Positive when the account is holding its normal balance.
     */
    protected function signed(int $difference, Account $account): int
    {
        return $this->expectedDirection($account) === 'credit' ? -$difference : $difference;
    }

    protected function expectedDirection(Account $account): string
    {
        $category = $account->accountType?->category;

        return match ($category) {
            'liability', 'equity', 'revenue' => 'credit',
            'asset', 'expense' => 'debit',
            default => (string) ($account->accountType?->normal_balance ?? 'debit'),
        };
    }
}
