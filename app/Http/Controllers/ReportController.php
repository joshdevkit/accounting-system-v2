<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Services\BalanceSheet;
use App\Services\IncomeStatement;
use App\Services\TrialBalance;
use App\Support\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reports are derived entirely from posted journal entry lines, so they are
 * always consistent with the ledger and never store anything of their own.
 */
class ReportController extends Controller
{
    public function trialBalance(Request $request, TrialBalance $trialBalance): Response
    {
        $company = CurrentCompany::find();

        $filters = $this->filters($request);
        $includeSummary = ! $request->has('summary') || $request->boolean('summary');

        $rows = $trialBalance->rows($company, $filters, $includeSummary);
        $totals = $trialBalance->totals($company, $filters);

        return Inertia::render('reports/trial-balance', [
            'company' => [
                'name' => $company->name,
                'code' => $company->code,
                'currency' => $company->base_currency_code,
            ],
            'generatedAt' => now()->toDateTimeString(),
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters + ['summary' => $includeSummary],
            'pickers' => $this->pickers($company),
            'unbalanced' => $totals['difference'] !== 0,
        ]);
    }

    public function ledger(Request $request, TrialBalance $trialBalance): Response|RedirectResponse
    {
        $company = CurrentCompany::find();

        // The sidebar links here without a selection, so the first account is
        // the sensible default. An id that is not part of this company's chart
        // is sent back to the picker with an explanation, never a 404.
        $requestedId = $request->filled('account') ? $request->integer('account') : null;

        $account = $company->accounts()
            ->with('accountType:id,name,category,normal_balance')
            ->withCount('children')
            ->when($requestedId, fn($query) => $query->whereKey($requestedId))
            ->orderBy('code')
            ->first();

        if (! $account) {
            if ($company->accounts()->exists()) {
                return to_route('reports.ledger')->with(
                    'error',
                    'That account is not part of this company chart of accounts.',
                );
            }

            return to_route('accounts.index')->with(
                'error',
                'Add at least one account before running the general ledger.',
            );
        }

        $filters = $this->filters($request);
        $filters['include_sub_accounts'] = $request->has('sub_accounts')
            ? $request->boolean('sub_accounts')
            : $account->children_count > 0;

        $ledger = $trialBalance->ledger($company, $account, $filters);

        return Inertia::render('reports/ledger', [
            'company' => [
                'name' => $company->name,
                'code' => $company->code,
                'currency' => $company->base_currency_code,
            ],
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'description' => $account->description,
                'category' => $account->accountType?->category,
                'normal_balance' => $account->accountType?->normal_balance,
                'children' => (int) $account->children_count,
            ],
            'ledger' => $ledger,
            'filters' => $filters + ['include_sub_accounts' => $filters['include_sub_accounts']],
            'accounts' => $company->accounts()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn(Account $account): array => [
                    'id' => $account->id,
                    'label' => "{$account->code} · {$account->name}",
                ]),
            'pickers' => $this->pickers($company),
        ]);
    }

    public function incomeStatement(Request $request, IncomeStatement $incomeStatement): Response
    {
        $company = CurrentCompany::find();

        $filters = $this->filters($request);
        $compare = $request->boolean('compare');

        if ($compare) {
            $comparison = $incomeStatement->generateWithComparison($company, $filters);
            $current = $comparison['current'];
            $previous = $comparison['previous'];
            $previousRange = $comparison['previous_range'];
            $hasComparison = $comparison['has_comparison'];
        } else {
            $current = $incomeStatement->generate($company, $filters);
            $previous = null;
            $previousRange = null;
            $hasComparison = false;
        }

        return Inertia::render('reports/income-statement', [
            'company' => [
                'name' => $company->name,
                'code' => $company->code,
                'currency' => $company->base_currency_code,
            ],
            'generatedAt' => now()->toDateTimeString(),
            'revenue' => $current['revenue'],
            'expenses' => $current['expenses'],
            'totals' => [
                'revenue' => $current['total_revenue_cents'],
                'expenses' => $current['total_expenses_cents'],
                'net_income' => $current['net_income_cents'],
            ],
            'previous' => $hasComparison ? [
                'revenue' => $previous['revenue'],
                'expenses' => $previous['expenses'],
                'totals' => [
                    'revenue' => $previous['total_revenue_cents'],
                    'expenses' => $previous['total_expenses_cents'],
                    'net_income' => $previous['net_income_cents'],
                ],
                'range' => $previousRange,
            ] : null,
            'compare' => $compare,
            'filters' => $filters,
            'pickers' => $this->pickers($company),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        return array_filter([
            'branch_id' => $request->filled('branch_id') ? $request->integer('branch_id') : null,
            'fiscal_year_id' => $request->filled('fiscal_year_id') ? $request->integer('fiscal_year_id') : null,
            'accounting_period_id' => $request->filled('accounting_period_id') ? $request->integer('accounting_period_id') : null,
            'from' => $request->filled('from') ? (string) $request->input('from') : null,
            'to' => $request->filled('to') ? (string) $request->input('to') : null,
            'statuses' => $request->boolean('include_drafts') ? ['posted', 'draft'] : ['posted'],
        ], fn(mixed $value): bool => $value !== null);
    }


    public function balanceSheet(Request $request, BalanceSheet $balanceSheet): Response
    {
        $company = CurrentCompany::find();

        $filters = [
            'branch_id' => $request->filled('branch_id') ? $request->integer('branch_id') : null,
            'as_of' => $request->filled('as_of') ? (string) $request->input('as_of') : now()->toDateString(),
        ];

        $result = $balanceSheet->generate($company, $filters);

        return Inertia::render('reports/balance-sheet', [
            'company' => [
                'name' => $company->name,
                'code' => $company->code,
                'currency' => $company->base_currency_code,
            ],
            'generatedAt' => now()->toDateTimeString(),
            'asOf' => $filters['as_of'],
            'assets' => $result['assets'],
            'liabilities' => $result['liabilities'],
            'equity' => $result['equity'],
            'totals' => [
                'assets' => $result['total_assets_cents'],
                'liabilities' => $result['total_liabilities_cents'],
                'equity' => $result['total_equity_cents'],
            ],
            'isBalanced' => $result['is_balanced'],
            'filters' => $filters,
            'branches' => $company->branches()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pickers(Company $company): array
    {
        return [
            'branches' => $company->branches()->orderBy('name')->get(['id', 'name', 'code']),
            'fiscalYears' => $company->fiscalYears()
                ->with(['periods' => fn($query) => $query->orderBy('period_number')])
                ->orderByDesc('year')
                ->get()
                ->map(fn($fiscalYear): array => [
                    'id' => $fiscalYear->id,
                    'year' => $fiscalYear->year,
                    'status' => $fiscalYear->status,
                    'start_date' => $fiscalYear->start_date->toDateString(),
                    'end_date' => $fiscalYear->end_date->toDateString(),
                    'periods' => $fiscalYear->periods->map(fn($period): array => [
                        'id' => $period->id,
                        'name' => $period->name,
                        'period_number' => $period->period_number,
                        'start_date' => $period->start_date->toDateString(),
                        'end_date' => $period->end_date->toDateString(),
                        'status' => $period->status,
                    ]),
                ]),
        ];
    }

    /**
     * Trial Balance report export
     */
    public function trialBalancePdf(Request $request, TrialBalance $trialBalance)
    {
        $company = CurrentCompany::find();

        $filters = $this->filters($request);
        $includeSummary = ! $request->has('summary') || $request->boolean('summary');

        $rows = $trialBalance->rows($company, $filters, $includeSummary);

        if ($request->boolean('only_with_activity')) {
            $rows = array_values(array_filter($rows, fn($row) => $row['debit'] !== 0 || $row['credit'] !== 0));
        }

        $totals = $trialBalance->totals($company, $filters);

        $pdf = Pdf::loadView('pdf.trial-balance', [
            'company' => ['name' => $company->name],
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
        ])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper('a4', 'portrait');

        return $pdf->stream('trial-balance-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Balance sheet pdf export
     */
    public function balanceSheetPdf(Request $request, BalanceSheet $balanceSheet)
    {
        $company = CurrentCompany::find();

        $filters = [
            'branch_id' => $request->filled('branch_id') ? $request->integer('branch_id') : null,
            'as_of' => $request->filled('as_of') ? (string) $request->input('as_of') : now()->toDateString(),
        ];

        $result = $balanceSheet->generate($company, $filters);

        $pdf = Pdf::loadView('pdf.balance-sheet', [
            'company' => ['name' => $company->name],
            'asOf' => $filters['as_of'],
            'assets' => $result['assets'],
            'liabilities' => $result['liabilities'],
            'equity' => $result['equity'],
            'totals' => [
                'assets' => $result['total_assets_cents'],
                'liabilities' => $result['total_liabilities_cents'],
                'equity' => $result['total_equity_cents'],
            ],
            'isBalanced' => $result['is_balanced'],
        ])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper('a4', 'portrait');

        return $pdf->stream('balance-sheet-' . now()->format('Y-m-d') . '.pdf');
    }


    public function incomeStatementPdf(Request $request, IncomeStatement $incomeStatement)
    {
        $company = CurrentCompany::find();

        $filters = $this->filters($request);

        $result = $incomeStatement->generate($company, $filters);

        $pdf = Pdf::loadView('pdf.income-statement', [
            'company' => ['name' => $company->name],
            'revenue' => $result['revenue'],
            'expenses' => $result['expenses'],
            'totals' => [
                'revenue' => $result['total_revenue_cents'],
                'expenses' => $result['total_expenses_cents'],
                'net_income' => $result['net_income_cents'],
            ],
            'filters' => $filters,
        ])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper('a4', 'portrait');

        return $pdf->stream('income-statement-' . now()->format('Y-m-d') . '.pdf');
    }
}
