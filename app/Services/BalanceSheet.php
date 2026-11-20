<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A snapshot of what the company owns, owes, and has left over for the
 * owner, as of a specific date.
 *
 * Assets = Liabilities + Equity, always. Net income earned so far (revenue
 * minus expenses, from the beginning of time through the as-of date) is
 * folded into Equity automatically as "Current earnings" — there is no
 * separate manual closing step in this system.
 */
class BalanceSheet
{
    /**
     * @param  array{branch_id?: int|null, as_of?: string|null}  $filters
     * @return array{
     *     assets: list<array<string, mixed>>,
     *     liabilities: list<array<string, mixed>>,
     *     equity: list<array<string, mixed>>,
     *     total_assets_cents: int,
     *     total_liabilities_cents: int,
     *     total_equity_cents: int,
     *     current_earnings_cents: int,
     *     is_balanced: bool
     * }
     */
    public function generate(Company $company, array $filters = []): array
    {
        $asOf = $filters['as_of'] ?? now()->toDateString();

        $accounts = $this->accounts($company);
        $aggregate = $this->aggregate($company, $filters, $asOf);

        $assets = [];
        $liabilities = [];
        $equity = [];

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($accounts as $account) {
            if ($account->hasChildren()) {
                continue;
            }

            $category = $account->accountType?->category;

            if (! in_array($category, ['asset', 'liability', 'equity'], true)) {
                continue;
            }

            $amounts = $aggregate[$account->id] ?? ['debit' => 0, 'credit' => 0];

            if ($amounts['debit'] === 0 && $amounts['credit'] === 0) {
                continue;
            }

            $row = ['id' => $account->id, 'code' => $account->code, 'name' => $account->name];

            if ($category === 'asset') {
                // Debit-normal.
                $net = $amounts['debit'] - $amounts['credit'];
                $row['amount_cents'] = $net;
                $assets[] = $row;
                $totalAssets += $net;
            } elseif ($category === 'liability') {
                // Credit-normal.
                $net = $amounts['credit'] - $amounts['debit'];
                $row['amount_cents'] = $net;
                $liabilities[] = $row;
                $totalLiabilities += $net;
            } else {
                // Equity — credit-normal.
                $net = $amounts['credit'] - $amounts['debit'];
                $row['amount_cents'] = $net;
                $equity[] = $row;
                $totalEquity += $net;
            }
        }

        // Fold in net income earned to date as a live "Current earnings" line,
        // so the books always balance without a manual closing entry.
        $currentEarnings = $this->currentEarnings($company, $filters, $asOf);

        if ($currentEarnings !== 0) {
            $equity[] = [
                'id' => 0,
                'code' => '',
                'name' => 'Current earnings',
                'amount_cents' => $currentEarnings,
            ];
            $totalEquity += $currentEarnings;
        }

        usort($assets, fn (array $a, array $b): int => $a['code'] <=> $b['code']);
        usort($liabilities, fn (array $a, array $b): int => $a['code'] <=> $b['code']);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets_cents' => $totalAssets,
            'total_liabilities_cents' => $totalLiabilities,
            'total_equity_cents' => $totalEquity,
            'current_earnings_cents' => $currentEarnings,
            'is_balanced' => $totalAssets === ($totalLiabilities + $totalEquity),
        ];
    }

    /**
     * Revenue minus expenses, from the beginning of time through the as-of
     * date — reuses the same category logic as IncomeStatement, but only
     * needs the two totals, not the per-account breakdown.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function currentEarnings(Company $company, array $filters, string $asOf): int
    {
        $accounts = $this->accounts($company);

        $earningsFilters = $filters;
        unset($earningsFilters['as_of']);
        $earningsFilters['to'] = $asOf;

        $aggregate = $this->aggregate($company, $earningsFilters, $asOf, forEarnings: true);

        $revenue = 0;
        $expenses = 0;

        foreach ($accounts as $account) {
            if ($account->hasChildren()) {
                continue;
            }

            $category = $account->accountType?->category;
            $amounts = $aggregate[$account->id] ?? ['debit' => 0, 'credit' => 0];

            if ($category === 'revenue') {
                $revenue += $amounts['credit'] - $amounts['debit'];
            } elseif ($category === 'expense') {
                $expenses += $amounts['debit'] - $amounts['credit'];
            }
        }

        return $revenue - $expenses;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{debit: int, credit: int}>
     */
    protected function aggregate(Company $company, array $filters, string $asOf, bool $forEarnings = false): array
    {
        $records = \App\Models\JournalEntryLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) as summed_debit, COALESCE(SUM(credit), 0) as summed_credit')
            ->whereHas('journalEntry', function (Builder $query) use ($company, $filters, $asOf): void {
                $query->where('journal_entries.company_id', $company->id)
                    ->where('journal_entries.status', 'posted')
                    ->whereDate('journal_entries.transaction_date', '<=', $asOf);

                if (! empty($filters['branch_id'])) {
                    $query->where('journal_entries.branch_id', $filters['branch_id']);
                }
            })
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
        return $company->accounts()
            ->with('accountType')
            ->withCount('children')
            ->orderBy('code')
            ->get()
            ->keyBy('id');
    }
}