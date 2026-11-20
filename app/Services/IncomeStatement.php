<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Revenue and expenses for a date range, netting to income or loss.
 *
 * Unlike TrialBalance, this only ever looks at revenue and expense category
 * accounts — assets, liabilities, and equity never appear here, since this
 * report answers "did the company make money in this period", not "what do
 * we own and owe".
 */
class IncomeStatement
{
    /**
     * @param  array{branch_id?: int|null, fiscal_year_id?: int|null, accounting_period_id?: int|null, from?: string|null, to?: string|null, statuses?: list<string>}  $filters
     * @return array{
     *     revenue: list<array<string, mixed>>,
     *     expenses: list<array<string, mixed>>,
     *     total_revenue_cents: int,
     *     total_expenses_cents: int,
     *     net_income_cents: int
     * }
     */
    public function generate(Company $company, array $filters = []): array
    {
        $accounts = $this->accounts($company);
        $aggregate = $this->aggregate($company, $filters);

        $revenueRows = [];
        $expenseRows = [];
        $totalRevenue = 0;
        $totalExpenses = 0;

        foreach ($accounts as $account) {
            // Summary (parent) accounts never post directly, so they carry
            // no activity of their own to report here.
            if ($account->hasChildren()) {
                continue;
            }

            $category = $account->accountType?->category;

            if (! in_array($category, ['revenue', 'expense'], true)) {
                continue;
            }

            $amounts = $aggregate[$account->id] ?? ['debit' => 0, 'credit' => 0];

            if ($amounts['debit'] === 0 && $amounts['credit'] === 0) {
                continue;
            }

            $row = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ];

            if ($category === 'revenue') {
                // Revenue is credit-normal: credit increases it, debit
                // (e.g. a sales return) reduces it.
                $net = $amounts['credit'] - $amounts['debit'];
                $row['amount_cents'] = $net;
                $revenueRows[] = $row;
                $totalRevenue += $net;
            } else {
                // Expense is debit-normal.
                $net = $amounts['debit'] - $amounts['credit'];
                $row['amount_cents'] = $net;
                $expenseRows[] = $row;
                $totalExpenses += $net;
            }
        }

        usort($revenueRows, fn(array $a, array $b): int => $a['code'] <=> $b['code']);
        usort($expenseRows, fn(array $a, array $b): int => $a['code'] <=> $b['code']);

        return [
            'revenue' => $revenueRows,
            'expenses' => $expenseRows,
            'total_revenue_cents' => $totalRevenue,
            'total_expenses_cents' => $totalExpenses,
            'net_income_cents' => $totalRevenue - $totalExpenses,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{debit: int, credit: int}>
     */
    protected function aggregate(Company $company, array $filters = []): array
    {
        $records = \App\Models\JournalEntryLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) as summed_debit, COALESCE(SUM(credit), 0) as summed_credit')
            ->whereHas('journalEntry', function (Builder $query) use ($company, $filters): void {
                $this->applyEntryFilters($query, $company, $filters);
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
     * @param  Builder<\App\Models\JournalEntryLine>  $query
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


    /**
     * Same figures as generate(), but also for a comparable prior period, plus
     * the variance between them.
     *
     * When the current range is exactly one calendar month or one calendar
     * year, the comparison is the previous calendar month/year — the natural
     * "vs last month" / "vs last year" reading. Any other range falls back to
     * an equal-length window immediately before it.
     *
     * Comparison only works when both `from` and `to` are set — an unbounded
     * "all posted entries" range has no natural prior period.
     *
     * @param  array<string, mixed>  $filters
     * @return array{current: array, previous: array|null, previous_range: array|null, has_comparison: bool}
     */
    public function generateWithComparison(Company $company, array $filters = []): array
    {
        $current = $this->generate($company, $filters);

        if (empty($filters['from']) || empty($filters['to'])) {
            return ['current' => $current, 'previous' => null, 'previous_range' => null, 'has_comparison' => false];
        }

        $from = \Carbon\Carbon::parse($filters['from']);
        $to = \Carbon\Carbon::parse($filters['to']);

        if ($from->isSameDay($from->copy()->startOfMonth()) && $to->isSameDay($to->copy()->endOfMonth()) && $from->isSameMonth($to)) {
            // A full calendar month — compare to the full previous month.
            $previousFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
            $previousTo = $previousFrom->copy()->endOfMonth();
        } elseif ($from->isSameDay($from->copy()->startOfYear()) && $to->isSameDay($to->copy()->endOfYear()) && $from->year === $to->year) {
            // A full calendar year — compare to the full previous year.
            $previousFrom = $from->copy()->subYear()->startOfYear();
            $previousTo = $previousFrom->copy()->endOfYear();
        } else {
            // Anything else — an equal-length window immediately before it.
            $days = $from->diffInDays($to);
            $previousTo = $from->copy()->subDay();
            $previousFrom = $previousTo->copy()->subDays($days);
        }

        $previousFilters = $filters;
        $previousFilters['from'] = $previousFrom->toDateString();
        $previousFilters['to'] = $previousTo->toDateString();
        // A prior period is a different fiscal year/period by definition, so
        // those scoping filters don't carry over — only the date range does.
        unset($previousFilters['fiscal_year_id'], $previousFilters['accounting_period_id']);

        $previous = $this->generate($company, $previousFilters);

        return [
            'current' => $current,
            'previous' => $previous,
            'previous_range' => ['from' => $previousFilters['from'], 'to' => $previousFilters['to']],
            'has_comparison' => true,
        ];
    }
}
