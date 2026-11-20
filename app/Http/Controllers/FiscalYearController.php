<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFiscalYearRequest;
use App\Http\Requests\UpdateFiscalYearRequest;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\FiscalYearService;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FiscalYearController extends Controller
{
    public function __construct(
        protected FiscalYearService $fiscalYears,
    ) {}

    public function index(): Response
    {
        $company = CurrentCompany::find();

        $years = $company->fiscalYears()
            ->with(['periods' => fn ($query) => $query->withCount('journalEntries')->orderBy('period_number')])
            ->withCount('journalEntries')
            ->orderByDesc('year')
            ->get();

        return Inertia::render('fiscal-years/index', [
            'fiscalYears' => $years->map(fn (FiscalYear $fiscalYear): array => [
                ...$this->row($fiscalYear),
                'periods' => $fiscalYear->periods->map(fn (AccountingPeriod $period): array => [
                    'id' => $period->id,
                    'period_number' => $period->period_number,
                    'name' => $period->name,
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'status' => $period->status,
                    'entries' => (int) $period->journal_entries_count,
                ]),
            ]),
            'stats' => [
                'years' => $years->count(),
                'open' => $years->where('status', 'open')->count(),
                'periods' => $years->sum(fn (FiscalYear $fiscalYear): int => $fiscalYear->periods->count()),
                'today' => now()->toDateString(),
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CurrentCompany::find();
        $year = (int) now()->format('Y');

        return Inertia::render('fiscal-years/create', [
            'defaults' => [
                'year' => $year,
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
            ],
            'periodsPerYear' => (int) config('accounting.fiscal_years.periods', 12),
            'autoGenerate' => (bool) config('accounting.fiscal_years.auto_generate_periods', true),
            'existingYears' => $company->fiscalYears()->orderBy('year')->pluck('year'),
        ]);
    }

    public function store(StoreFiscalYearRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $fiscalYear = $this->fiscalYears->create($company, $request->validated());

        $periods = $fiscalYear->periods()->count();

        return to_route('fiscal-years.index')->with(
            'success',
            "Fiscal year {$fiscalYear->year} created with {$periods} accounting period".($periods === 1 ? '' : 's').'.',
        );
    }

    public function edit(FiscalYear $fiscalYear): Response
    {
        $this->guardCompany($fiscalYear);

        $fiscalYear->load(['periods' => fn ($query) => $query->withCount('journalEntries')->orderBy('period_number')]);

        return Inertia::render('fiscal-years/edit', [
            'fiscalYear' => [
                ...$this->row($fiscalYear),
                'periods' => $fiscalYear->periods->map(fn (AccountingPeriod $period): array => [
                    'id' => $period->id,
                    'period_number' => $period->period_number,
                    'name' => $period->name,
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'status' => $period->status,
                    'entries' => (int) $period->journal_entries_count,
                    'drafts' => $period->journalEntries()->where('status', 'draft')->count(),
                ]),
            ],
            'periodsPerYear' => (int) config('accounting.fiscal_years.periods', 12),
        ]);
    }

    public function update(UpdateFiscalYearRequest $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $this->guardCompany($fiscalYear);

        $this->fiscalYears->update($fiscalYear, $request->validated());

        return to_route('fiscal-years.index')->with('success', "Fiscal year {$fiscalYear->year} updated.");
    }

    public function destroy(FiscalYear $fiscalYear): RedirectResponse
    {
        $this->guardCompany($fiscalYear);

        $this->fiscalYears->delete($fiscalYear);

        return to_route('fiscal-years.index')->with('success', 'Fiscal year removed.');
    }

    /**
     * Fill in periods that are still missing (after changing the dates, or
     * when auto generation was disabled).
     */
    public function generatePeriods(FiscalYear $fiscalYear): RedirectResponse
    {
        $this->guardCompany($fiscalYear);

        $created = $this->fiscalYears->generatePeriods($fiscalYear->load('periods'));

        return to_route('fiscal-years.edit', $fiscalYear)->with(
            'success',
            $created === 0
                ? 'Every period for this fiscal year already exists.'
                : "{$created} period".($created === 1 ? '' : 's')." generated.",
        );
    }

    public function updateStatus(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $this->guardCompany($fiscalYear);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        $this->fiscalYears->setStatus($fiscalYear, $validated['status']);

        return to_route('fiscal-years.index')->with(
            'success',
            $fiscalYear->status === 'closed'
                ? "Fiscal year {$fiscalYear->year} closed. Its periods are closed too."
                : "Fiscal year {$fiscalYear->year} reopened.",
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(FiscalYear $fiscalYear): array
    {
        return [
            'id' => $fiscalYear->id,
            'year' => $fiscalYear->year,
            'status' => $fiscalYear->status,
            'start_date' => $fiscalYear->start_date->toDateString(),
            'end_date' => $fiscalYear->end_date->toDateString(),
            'entries' => (int) ($fiscalYear->journal_entries_count ?? 0),
            'period_count' => $fiscalYear->relationLoaded('periods') ? $fiscalYear->periods->count() : null,
        ];
    }

    protected function guardCompany(FiscalYear $fiscalYear): void
    {
        abort_unless($fiscalYear->company_id === CurrentCompany::find()->id, 404);
    }
}
