<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PosEntry;
use App\Services\FiscalYearService;
use App\Services\TrialBalance;
use App\Support\CurrentCompany;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        protected FiscalYearService $fiscalYears,
        protected TrialBalance $trialBalance,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $company = CurrentCompany::get();

        if (! $company) {
            return to_route('company.edit')
                ->with('error', 'Set up your company details to get started.');
        }

        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $scope = array_filter(['branch_id' => $branchId]);
        $today = now()->toDateString();

        $period = $this->fiscalYears->currentPeriod($company);
        $fiscalYear = $period?->fiscalYear
            ?? $company->fiscalYears()->orderByDesc('year')->first();

        $postedToday = $company->posEntries()
            ->whereDate('business_date', $today)
            ->where('status', 'posted');

        $tenders = $this->tenders($company, $today, $branchId);

        $monthly = $this->monthlyFigures($company, $fiscalYear, $branchId);

        $aging = $this->agingFigures($company, $branchId);

        return Inertia::render('dashboard', [
            //chart data
            'cashFlow' => $monthly['cash_flow'],
            'incomeExpense' => $monthly['income_expense'],
            'receivables' => $aging['receivables'],
            'payables' => $aging['payables'],

            'company' => [
                'name' => $company->name,
                'code' => $company->code,
                'currency' => $company->base_currency_code,
                'is_active' => $company->is_active,
            ],
            'businessDate' => $today,
            'branchId' => $branchId,
            'branches' => $company->branches()->orderBy('name')->get(['id', 'name', 'code', 'is_active']),
            'fiscalYear' => $fiscalYear ? [
                'id' => $fiscalYear->id,
                'year' => $fiscalYear->year,
                'status' => $fiscalYear->status,
                'start_date' => $fiscalYear->start_date->toDateString(),
                'end_date' => $fiscalYear->end_date->toDateString(),
            ] : null,
            'period' => $period ? [
                'id' => $period->id,
                'name' => $period->name,
                'number' => $period->period_number,
                'status' => $period->status,
                'allows_posting' => $period->allowsPosting(),
            ] : null,
            'kpis' => [
                [
                    'key' => 'cash',
                    'label' => 'Cash and equivalents',
                    'cents' => $this->trialBalance->balanceCentsForCodes(
                        $company,
                        (array) config('accounting.dashboard.cash_accounts'),
                        $scope,
                    ),
                    'hint' => 'Posted ledger balance',
                ],
                [
                    'key' => 'net_sales',
                    'label' => 'Net sales, today',
                    'cents' => $this->netSalesToday($company, $today, $branchId),
                    'hint' => 'From posted POS summaries',
                ],
                [
                    'key' => 'receivables',
                    'label' => 'Guests and corporate AR',
                    'cents' => $this->trialBalance->balanceCentsForCodes(
                        $company,
                        (array) config('accounting.dashboard.receivable_accounts'),
                        $scope,
                    ),
                    'hint' => 'Outstanding balances',
                ],
                [
                    'key' => 'unposted',
                    'label' => 'Unposted entries',
                    'count' => $company->journalEntries()->where('status', 'draft')->count()
                        + $company->posEntries()->where('status', 'draft')->count(),
                    'hint' => 'Awaiting review',
                ],
            ],
            'tenders' => $tenders,
            'posSummaries' => [
                'posted' => (clone $postedToday)->count(),
                'drafts' => $company->posEntries()->whereDate('business_date', $today)->where('status', 'draft')->count(),
            ],
            'recentEntries' => $company->journalEntries()
                ->with(['branch:id,name', 'accountingPeriod:id,name,status'])
                ->withSum('lines', 'debit')
                ->withCount('lines')
                ->when($branchId, fn($query) => $query->where('branch_id', $branchId))
                // Paid purchases are already settled in the ledger, so keep them
                // out of this "recent activity" list.
                ->withoutReleasedPurchases()
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn(JournalEntry $entry): array => [
                    'id' => $entry->id,
                    'reference_no' => $entry->reference_no,
                    'transaction_date' => $entry->transaction_date->toDateString(),
                    'description' => $entry->description,
                    'status' => $entry->status,
                    'source_type' => $entry->source_type,
                    'branch' => $entry->branch?->name,
                    'period' => $entry->accountingPeriod?->name,
                    'line_count' => (int) $entry->lines_count,
                    'cents' => Money::cents($entry->lines_sum_debit),
                ]),
            'alerts' => $this->alerts($company, $period),
        ]);
    }

    protected function netSalesToday(Company $company, string $today, ?int $branchId): int
    {
        $sum = $company->posEntries()
            ->whereDate('business_date', $today)
            ->where('status', 'posted')
            ->when($branchId, fn($query) => $query->where('branch_id', $branchId))
            ->sum('net_sales');

        return Money::cents($sum);
    }

    /**
     * What the tills actually took in today, per payment method.
     *
     * @return list<array{field: string, label: string, cents: int}>
     */
    protected function tenders(Company $company, string $today, ?int $branchId): array
    {
        $fields = array_values(array_unique(array_merge(
            (array) config('accounting.pos.payment_fields'),
            (array) config('accounting.pos.amount_fields'),
        )));

        $totals = $company->posEntries()
            ->whereDate('business_date', $today)
            ->whereIn('status', ['posted', 'draft'])
            ->when($branchId, fn($query) => $query->where('branch_id', $branchId))
            ->selectRaw(implode(', ', array_map(
                fn(string $field): string => "COALESCE(SUM({$field}), 0) as {$field}",
                $fields,
            )))
            ->first();

        $labels = (array) config('accounting.pos.payment_labels');
        $paymentFields = (array) config('accounting.pos.payment_fields');

        $tenders = [];

        foreach ($paymentFields as $field) {
            $tenders[] = [
                'field' => $field,
                'label' => (string) ($labels[$field] ?? $field),
                'cents' => Money::cents($totals?->{$field}),
            ];
        }

        return $tenders;
    }

    /**
     * @return list<array{level: string, message: string, href?: string, action?: string}>
     */
    protected function alerts(Company $company, mixed $period): array
    {
        $alerts = [];

        if (! $company->is_active) {
            $alerts[] = [
                'level' => 'error',
                'message' => 'This company is inactive. Nothing should be posted until it is reactivated.',
                'href' => '/settings/company',
                'action' => 'Review company settings',
            ];
        }

        if (! $period) {
            $alerts[] = [
                'level' => 'error',
                'message' => 'There is no fiscal year for today, so journal entries cannot be filed yet.',
                'href' => '/fiscal-years/create',
                'action' => 'Create a fiscal year',
            ];
        } elseif (! $period->allowsPosting()) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "The current period ({$period->name}) is closed. Reopen it, or create the next period, to keep posting.",
                'href' => '/fiscal-years',
                'action' => 'Review periods',
            ];
        }

        if ($company->accounts()->count() === 0) {
            $alerts[] = [
                'level' => 'error',
                'message' => 'The chart of accounts is empty, so there is nowhere to post to.',
                'href' => '/accounts',
                'action' => 'Set up accounts',
            ];
        }

        if ($company->branches()->count() === 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'No branches yet, so POS day summaries have nowhere to be assigned.',
                'href' => '/branches/create',
                'action' => 'Add a branch',
            ];
        }

        $drafts = PosEntry::query()
            ->where('company_id', $company->id)
            ->where('status', 'draft')
            ->count();

        if ($drafts > 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => sprintf(
                    '%s still in draft. Posting %s writes the ledger entries.',
                    Str::plural('POS day summary', $drafts),
                    $drafts === 1 ? 'it' : 'them',
                ),
                'href' => '/pos-entries?status=draft',
                'action' => 'Review drafts',
            ];
        }

        return $alerts;
    }

    /**
     * Cash in/out and income/expense, month by month, for the given fiscal year.
     *
     * @return array{
     *     months: list<string>,
     *     cash_flow: list<array{month: string, cash_in: int, cash_out: int}>,
     *     income_expense: list<array{month: string, income: int, expense: int}>
     * }
     */
    protected function monthlyFigures(Company $company, ?FiscalYear $fiscalYear, ?int $branchId): array
    {
        if (! $fiscalYear) {
            return ['months' => [], 'cash_flow' => [], 'income_expense' => []];
        }

        $cashCodes = (array) config('accounting.dashboard.cash_accounts');

        $lines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->join('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', 'posted')
            ->when($branchId, fn($query) => $query->where('journal_entries.branch_id', $branchId))
            ->select([
                DB::raw("DATE_FORMAT(journal_entries.transaction_date, '%Y-%m') as ym"),
                'accounts.code',
                'account_types.category',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as credit'),
            ])
            ->groupBy('ym', 'accounts.code', 'account_types.category')
            ->get();

        $months = [];
        $start = $fiscalYear->start_date->copy()->startOfMonth();
        $end = $fiscalYear->end_date->copy()->startOfMonth();

        $guard = 0;

        while ($start->lte($end) && $guard++ < 36) {
            $months[$start->format('Y-m')] = $start->format('M Y');
            $start = $start->addMonth();
        }

        $cashFlow = [];
        $incomeExpense = [];

        foreach ($months as $ym => $label) {
            $cashIn = 0;
            $cashOut = 0;
            $income = 0;
            $expense = 0;

            foreach ($lines->where('ym', $ym) as $row) {
                $debit = Money::cents($row->debit);
                $credit = Money::cents($row->credit);

                if (in_array($row->code, $cashCodes, true)) {
                    $cashIn += $debit;
                    $cashOut += $credit;
                }

                if ($row->category === 'revenue') {
                    $income += $credit - $debit;
                } elseif ($row->category === 'expense') {
                    $expense += $debit - $credit;
                }
            }

            $cashFlow[] = ['month' => $label, 'cash_in' => $cashIn, 'cash_out' => $cashOut];
            $incomeExpense[] = ['month' => $label, 'income' => $income, 'expense' => $expense];
        }

        return [
            'months' => array_values($months),
            'cash_flow' => $cashFlow,
            'income_expense' => $incomeExpense,
        ];
    }


    /**
     * Total receivables and payables, split into current vs overdue based on
     * due_date. Only unpaid, posted records count — a paid or draft one
     * carries no outstanding balance.
     *
     * @return array{
     *     receivables: array{total: int, current: int, overdue: int},
     *     payables: array{total: int, current: int, overdue: int}
     * }
     */
    protected function agingFigures(Company $company, ?int $branchId): array
    {
        $today = now()->toDateString();

        $sales = $company->sales()
            ->where('status', 'posted')
            ->where('payment_status', 'unpaid')
            ->when($branchId, fn($query) => $query->where('branch_id', $branchId))
            ->get(['taxable_amount', 'vat_amount', 'withholding_tax_amount', 'due_date']);

        $purchases = $company->purchases()
            ->where('status', 'posted')
            ->where('payment_status', 'unpaid')
            ->when($branchId, fn($query) => $query->where('branch_id', $branchId))
            ->get(['taxable_amount', 'vat_amount', 'withholding_tax_amount', 'due_date']);

        $receivables = ['total' => 0, 'current' => 0, 'overdue' => 0];
        $payables = ['total' => 0, 'current' => 0, 'overdue' => 0];

        foreach ($sales as $sale) {
            $net = Money::cents($sale->taxable_amount) + Money::cents($sale->vat_amount) - Money::cents($sale->withholding_tax_amount);
            $overdue = $sale->due_date && $sale->due_date->toDateString() < $today;

            $receivables['total'] += $net;
            $receivables[$overdue ? 'overdue' : 'current'] += $net;
        }

        foreach ($purchases as $purchase) {
            $net = Money::cents($purchase->taxable_amount) + Money::cents($purchase->vat_amount) - Money::cents($purchase->withholding_tax_amount);
            $overdue = $purchase->due_date && $purchase->due_date->toDateString() < $today;

            $payables['total'] += $net;
            $payables[$overdue ? 'overdue' : 'current'] += $net;
        }

        return ['receivables' => $receivables, 'payables' => $payables];
    }
}
