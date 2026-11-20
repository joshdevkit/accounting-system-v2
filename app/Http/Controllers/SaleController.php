<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Imports\SalesImportPreview;
use App\Models\Account;
use App\Models\Sale;
use App\Services\SalePostingService;
use App\Support\CurrentCompany;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class SaleController extends Controller
{
    public function __construct(
        protected SalePostingService $posting,
    ) {}

    public function index(Request $request): Response
    {
        $company = CurrentCompany::find();

        $entries = $company->sales()
            ->with(['branch:id,name,code', 'creditAccount:id,code,name', 'journalEntry:id,reference_no,status'])
            ->when($request->filled('status'), fn($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('sales/index', [
            'entries' => $entries->through(fn(Sale $sale): array => [
                'id' => $sale->id,
                'sale_date' => $sale->sale_date->toDateString(),
                'customer_name' => $sale->customer_name,
                'reference_no' => $sale->reference_no,
                'status' => $sale->status,
                'payment_status' => $sale->payment_status,
                'net_receivable_cents' => Money::cents($sale->netReceivable()),
                'branch' => $sale->branch?->name,
                'account' => $sale->creditAccount?->name,
                'journal_entry' => $sale->journalEntry ? [
                    'id' => $sale->journalEntry->id,
                    'reference_no' => $sale->journalEntry->reference_no,
                ] : null,
            ])->all(),
            'currency' => $company->base_currency_code,
            'branches' => $company->branches()->active()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function create(): Response
    {
        $company = CurrentCompany::find();

        return Inertia::render('sales/create', [
            'branches' => $company->branches()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'accountOptions' => $this->accountOptions($company->id),
            'withholdingRates' => $this->withholdingRates(),
            'businessSources' => $this->businessSources(),
            'discounts' => $this->discounts(),
            'vatRate' => (float) config('accounting.sales.vat_rate', 0.12),
            'currency' => $company->base_currency_code,
            'defaults' => [
                'sale_date' => now()->toDateString(),
                'business_source' => '',
                'discount_type' => 'none',
            ],
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $taxes = $this->posting->computeTaxes(
            (float) $request->validated('taxable_amount'),
            $request->validated('tax_type'),
            (float) $request->validated('withholding_tax_rate'),
        );

        $dueDate = $request->validated('due_date')
            ?: \Carbon\Carbon::parse($request->validated('sale_date'))->addDays(30)->toDateString();


        $sale = $company->sales()->create([
            ...collect($request->validated())->except('post_now')->all(),
            'due_date' => $dueDate,
            ...$taxes,
            'status' => 'draft',
            'created_by' => $request->user()?->id,
        ]);

        if ($request->boolean('post_now')) {
            $this->posting->post($sale, $request->user());

            return to_route('sales.show', $sale)->with('success', 'Sale recorded and posted.');
        }

        return to_route('sales.show', $sale)->with('success', 'Sale saved as a draft.');
    }

    public function show(Sale $sale): Response
    {
        $this->guardCompany($sale);

        $sale->load([
            'branch:id,name,code',
            'creditAccount:id,code,name',
            'journalEntry.lines.account:id,code,name',
            'paymentJournalEntry.lines.account:id,code,name',
            'creator:id,name',
            'poster:id,name',
            'payer:id,name',
        ]);

        return Inertia::render('sales/show', [
            'sale' => [
                'id' => $sale->id,
                'sale_date' => $sale->sale_date->toDateString(),
                'reference_no' => $sale->reference_no,
                'customer_name' => $sale->customer_name,
                'taxable_amount_cents' => Money::cents($sale->taxable_amount),
                'vat_amount_cents' => Money::cents($sale->vat_amount),
                'withholding_tax_rate' => (float) $sale->withholding_tax_rate,
                'withholding_tax_amount_cents' => Money::cents($sale->withholding_tax_amount),
                'gross_amount_cents' => Money::cents($sale->grossAmount()),
                'net_receivable_cents' => Money::cents($sale->netReceivable()),
                'tax_type' => $sale->tax_type,
                'payment_status' => $sale->payment_status,
                'settlement_account' => $sale->settlement_account,
                'notes' => $sale->notes,
                'status' => $sale->status,
                'branch' => $sale->branch?->name,
                'account' => $sale->creditAccount ? [
                    'code' => $sale->creditAccount->code,
                    'name' => $sale->creditAccount->name,
                ] : null,
                'created_by' => $sale->creator?->name,
                'posted_by' => $sale->poster?->name,
                'posted_at' => $sale->posted_at?->toDateTimeString(),
                'paid_by' => $sale->payer?->name,
                'paid_at' => $sale->paid_at?->toDateTimeString(),
            ],
            'journalEntry' => $sale->journalEntry ? [
                'id' => $sale->journalEntry->id,
                'reference_no' => $sale->journalEntry->reference_no,
                'status' => $sale->journalEntry->status,
                'lines' => $sale->journalEntry->lines->map(fn($line) => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit_cents' => Money::cents($line->debit),
                    'credit_cents' => Money::cents($line->credit),
                ]),
            ] : null,
            'paymentJournalEntry' => $sale->paymentJournalEntry ? [
                'id' => $sale->paymentJournalEntry->id,
                'reference_no' => $sale->paymentJournalEntry->reference_no,
                'lines' => $sale->paymentJournalEntry->lines->map(fn($line) => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit_cents' => Money::cents($line->debit),
                    'credit_cents' => Money::cents($line->credit),
                ]),
            ] : null,
            'actions' => [
                'can_edit' => $sale->isEditable(),
                'can_post' => $sale->isDraft(),
                'can_void' => ! $sale->isVoided(),
                'can_delete' => $sale->isDraft(),
                'can_mark_paid' => $sale->canMarkPaid(),
            ],
            'settlementOptions' => $this->settlementOptions(),
            'currency' => $sale->company->base_currency_code,
        ]);
    }

    public function edit(Sale $sale): Response|RedirectResponse
    {
        $this->guardCompany($sale);

        if (! $sale->isEditable()) {
            return to_route('sales.show', $sale)->with('error', 'Posted sales can only be voided.');
        }

        $company = $sale->company;

        return Inertia::render('sales/edit', [
            'sale' => [
                'id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'sale_date' => $sale->sale_date->toDateString(),
                'credit_account_id' => $sale->credit_account_id,
                'reference_no' => $sale->reference_no,
                'customer_name' => $sale->customer_name,
                'taxable_amount' => (float) $sale->taxable_amount,
                'tax_type' => $sale->tax_type,
                'business_source' => $sale->business_source,
                'discount_type' => $sale->discount_type,
                'withholding_tax_rate' => (float) $sale->withholding_tax_rate,
                'notes' => $sale->notes,
            ],
            'branches' => $company->branches()->orderBy('name')->get(['id', 'name', 'code']),
            'accountOptions' => $this->accountOptions($company->id),
            'withholdingRates' => $this->withholdingRates(),
            'businessSources' => $this->businessSources(),
            'discounts' => $this->discounts(),
            'vatRate' => (float) config('accounting.sales.vat_rate', 0.12),
            'currency' => $company->base_currency_code,
        ]);
    }

    public function update(UpdateSaleRequest $request, Sale $sale): RedirectResponse
    {
        if (! $sale->isEditable()) {
            return to_route('sales.show', $sale)->with('error', 'Only draft sales can be changed.');
        }

        $taxes = $this->posting->computeTaxes(
            (float) $request->validated('taxable_amount'),
            $request->validated('tax_type'),
            (float) $request->validated('withholding_tax_rate'),
        );

        $sale->fill([
            ...collect($request->validated())->except('post_now')->all(),
            ...$taxes,
        ])->save();

        if ($request->boolean('post_now')) {
            $this->posting->post($sale, $request->user());

            return to_route('sales.show', $sale)->with('success', 'Sale posted.');
        }

        return to_route('sales.show', $sale)->with('success', 'Sale updated.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $this->guardCompany($sale);

        if (! $sale->isDraft()) {
            throw ValidationException::withMessages([
                'sale' => 'Only draft sales can be deleted. Void a posted one instead.',
            ]);
        }

        $sale->delete();

        return to_route('sales.index')->with('success', 'Draft sale removed.');
    }

    public function post(Request $request, Sale $sale): RedirectResponse
    {
        $this->guardCompany($sale);

        $this->posting->post($sale, $request->user());

        return to_route('sales.show', $sale)->with('success', 'Sale posted.');
    }

    public function receive(Request $request, Sale $sale): RedirectResponse
    {
        $this->guardCompany($sale);

        $validated = $request->validate([
            'settlement_account' => [
                'required',
                Rule::in(array_keys((array) config('accounting.sales.settlement_accounts'))),
            ],
            'payment_date' => ['nullable', 'date'],
        ]);

        $this->posting->receive(
            $sale,
            $validated['settlement_account'],
            $request->user(),
            $validated['payment_date'] ?? null,
        );

        return to_route('sales.show', $sale)->with('success', 'Payment recorded.');
    }

    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $this->guardCompany($sale);

        $validated = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        $this->posting->void($sale, $validated['transaction_date'] ?? now(), $request->user());

        return to_route('sales.show', $sale)->with('success', 'Sale voided.');
    }

    public function preview(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $import = new SalesImportPreview();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Could not read that file: ' . $e->getMessage()]);
        }

        $preview = $import->toArray($request->file('file')->getClientOriginalName());

        $company = CurrentCompany::find();
        $entries = $company->sales()
            ->with(['branch:id,name,code', 'creditAccount:id,code,name', 'journalEntry:id,reference_no,status'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('sales/index', [
            'entries' => $entries->through(fn(Sale $sale): array => [
                'id' => $sale->id,
                'sale_date' => $sale->sale_date->toDateString(),
                'customer_name' => $sale->customer_name,
                'reference_no' => $sale->reference_no,
                'status' => $sale->status,
                'payment_status' => $sale->payment_status,
                'net_receivable_cents' => Money::cents($sale->netReceivable()),
                'branch' => $sale->branch?->name,
                'account' => $sale->creditAccount?->name,
                'journal_entry' => $sale->journalEntry ? [
                    'id' => $sale->journalEntry->id,
                    'reference_no' => $sale->journalEntry->reference_no,
                ] : null,
            ])->all(),
            'currency' => $company->base_currency_code,
            'branches' => $company->branches()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'preview' => $preview,
        ]);
    }

    /**
     * @return list<array{category: string, options: list<array{id: int, label: string}>}>
     */
    protected function accountOptions(int $companyId): array
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->withCount('children')
            ->with('accountType')
            ->orderBy('code')
            ->get()
            ->filter(fn(Account $account) => (int) $account->children_count === 0)
            ->groupBy(fn(Account $account) => $account->accountType?->name ?? 'Accounts')
            ->map(fn($accounts, $category) => [
                'category' => $category,
                'options' => $accounts->map(fn(Account $account) => [
                    'id' => $account->id,
                    'label' => "{$account->code} · {$account->name}",
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function withholdingRates(): array
    {
        return collect((array) config('accounting.sales.withholding_tax_rates'))
            ->map(fn(string $label, string $rate) => ['value' => $rate, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function businessSources(): array
    {
        return collect((array) config('accounting.sales.business_sources'))
            ->map(fn(string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function discounts(): array
    {
        return collect((array) config('accounting.sales.discounts'))
            ->map(fn(string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function settlementOptions(): array
    {
        $labels = (array) config('accounting.sales.settlement_labels');

        return collect((array) config('accounting.sales.settlement_accounts'))
            ->keys()
            ->map(fn(string $key) => ['value' => $key, 'label' => $labels[$key] ?? $key])
            ->values()
            ->all();
    }

    protected function guardCompany(Sale $sale): void
    {
        abort_unless($sale->company_id === CurrentCompany::find()->id, 404);
    }
}
