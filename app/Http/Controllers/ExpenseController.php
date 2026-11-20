<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Account;
use App\Models\Expense;
use App\Services\ExpensePostingService;
use App\Support\CurrentCompany;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpensePostingService $posting,
    ) {}

    public function index(Request $request): Response
    {
        $company = CurrentCompany::find();

        $entries = $company->expenses()
            ->with(['branch:id,name,code', 'journalEntry:id,reference_no,status'])
            ->when($request->filled('status'), fn($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('expenses/index', [
            'entries' => $entries->through(fn(Expense $expense): array => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date->toDateString(),
                'payee' => $expense->contact?->name
                        ?? $expense->vendor_name,
                'reference_no' => $expense->reference_no,
                'status' => $expense->status,
                'payment_status' => $expense->payment_status,
                'net_payable_cents' => Money::cents($expense->netPayable()),
                'branch' => $expense->branch?->name,
                'journal_entry' => $expense->journalEntry ? [
                    'id' => $expense->journalEntry->id,
                    'reference_no' => $expense->journalEntry->reference_no,
                ] : null,
            ])->all(),
            'currency' => $company->base_currency_code,
        ]);
    }

    public function create(): Response
    {
        $company = CurrentCompany::find();

        return Inertia::render('expenses/create', [
            'branches' => $this->branchOptions($company),
            'contacts' => $company->contacts()
                ->where('is_active', true)
                ->whereIn('type', ['vendor', 'both'])
                ->orderBy('name')
                ->get(['id', 'name', 'tax_identification_number', 'address', 'default_payment_terms_days']),
            'accountOptions' => $this->accountOptions($company->id),
            'withholdingRates' => $this->withholdingRates(),
            'vatRate' => (float) config('accounting.expenses.vat_rate', 0.12),
            'currency' => $company->base_currency_code,
            'defaults' => [
                'expense_date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $header = collect($request->validated())->except(['post_now', 'lines'])->all();
        $linesInput = $request->validated('lines');

        $expense = DB::transaction(function () use ($company, $header, $linesInput, $request) {
            $expense = $company->expenses()->create([
                ...$header,
                'status' => 'draft',
                'created_by' => $request->user()?->id,
            ]);

            foreach ($linesInput as $index => $line) {
                $expense->lines()->create([
                    'account_id' => $line['account_id'],
                    'particulars' => $line['particulars'],
                    'quantity' => $line['quantity'],
                    'uom' => $line['uom'] ?? null,
                    'unit_price' => $line['unit_price'],
                    'amount' => $line['amount'],
                    'tax_type' => $line['tax_type'],
                    'vat_amount' => $this->posting->computeLineVat((float) $line['amount'], $line['tax_type']),
                    'withholding_tax_rate' => $line['withholding_tax_rate'],
                    'withholding_tax_amount' => $this->posting->computeLineWithholding(
                        (float) $line['amount'],
                        (float) $line['withholding_tax_rate'],
                    ),
                    'description' => $line['description'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            return $expense;
        });

        if ($request->boolean('post_now')) {
            $this->posting->post($expense, $request->user());

            return to_route('expenses.show', $expense)->with('success', 'Expense recorded and posted.');
        }

        return to_route('expenses.show', $expense)->with('success', 'Expense saved as a draft.');
    }

    public function show(Expense $expense): Response
    {
        $this->guardCompany($expense);

        $expense->load([
            'branch:id,name,code',
            'lines.account:id,code,name',
            'journalEntry.lines.account:id,code,name',
              'intercompanyJournalEntry.lines.account:id,code,name',
            'paymentJournalEntry.lines.account:id,code,name',
            'creator:id,name',
            'poster:id,name',
            'payer:id,name',
        ]);

        return Inertia::render('expenses/show', [
            'expense' => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date->toDateString(),
                'reference_no' => $expense->reference_no,
                'payee' => $expense->payee,
                'description' => $expense->description,
                'taxable_amount_cents' => Money::cents($expense->taxableAmount()),
                'vat_amount_cents' => Money::cents($expense->vatAmount()),
                'withholding_tax_amount_cents' => Money::cents($expense->withholdingTaxAmount()),
                'gross_amount_cents' => Money::cents($expense->grossAmount()),
                'net_payable_cents' => Money::cents($expense->netPayable()),
                'payment_status' => $expense->payment_status,
                'settlement_account' => $expense->settlement_account,
                'bank_name' => $expense->bank_name,
                'check_number' => $expense->check_number,
                'notes' => $expense->notes,
                'status' => $expense->status,
                'branch' => $expense->branch?->name,
                'lines' => $expense->lines->map(fn($line) => [
                    'particulars' => $line->particulars,
                    'quantity' => (float) ($line->quantity ?? 1),
                    'uom' => $line->uom,
                    'unit_price' => (float) ($line->unit_price ?? $line->amount),
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'amount_cents' => Money::cents($line->amount),
                    'tax_type' => $line->tax_type,
                    'vat_amount_cents' => Money::cents($line->vat_amount),
                    'withholding_tax_rate' => (float) $line->withholding_tax_rate,
                    'withholding_tax_amount_cents' => Money::cents($line->withholding_tax_amount),
                ]),
                'created_by' => $expense->creator?->name,
                'posted_by' => $expense->poster?->name,
                'posted_at' => $expense->posted_at?->toDateTimeString(),
                'paid_by' => $expense->payer?->name,
                'paid_at' => $expense->paid_at?->toDateTimeString(),
            ],
            'journalEntry' => $expense->journalEntry ? [
                'id' => $expense->journalEntry->id,
                'reference_no' => $expense->journalEntry->reference_no,
                'lines' => $expense->journalEntry->lines->map(fn($line) => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit_cents' => Money::cents($line->debit),
                    'credit_cents' => Money::cents($line->credit),
                ]),
            ] : null,
              'intercompanyJournalEntry' => $expense->intercompanyJournalEntry ? [
                  'id' => $expense->intercompanyJournalEntry->id,
                  'reference_no' => $expense->intercompanyJournalEntry->reference_no,
                  'status' => $expense->intercompanyJournalEntry->status,
                  'lines' => $expense->intercompanyJournalEntry->lines->map(fn ($line) => [
                      'account_code' => $line->account?->code,
                      'account_name' => $line->account?->name,
                      'debit_cents' => Money::cents($line->debit),
                      'credit_cents' => Money::cents($line->credit),
                  ]),
              ] : null,
              'intercompanyVoucher' => \App\Models\IntercompanyVoucher::query()
                  ->where('source_type', 'expense')->where('source_id', $expense->id)
                  ->where('company_id', $expense->company_id)->first(['id', 'status']),
              'intercompanyAccounts' => $expense->status === 'posted' && ! $expense->intercompanyJournalEntry
                  ? app(\App\Services\IntercompanyJournalService::class)->accountOptions($expense->company)
                  : [],
              'intercompanyBranches' => $expense->status === 'posted' && ! $expense->intercompanyJournalEntry
                  ? app(\App\Services\IntercompanyJournalService::class)->branchOptions($expense->company)
                  : [],
            'paymentJournalEntry' => $expense->paymentJournalEntry ? [
                'id' => $expense->paymentJournalEntry->id,
                'reference_no' => $expense->paymentJournalEntry->reference_no,
                'lines' => $expense->paymentJournalEntry->lines->map(fn($line) => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit_cents' => Money::cents($line->debit),
                    'credit_cents' => Money::cents($line->credit),
                ]),
            ] : null,
            'actions' => [
                'can_edit' => $expense->isEditable(),
                'can_post' => $expense->isDraft(),
                'can_void' => ! $expense->isVoided(),
                'can_delete' => $expense->isDraft(),
                'can_mark_paid' => $expense->canMarkPaid(),
            ],
            'settlementOptions' => $this->settlementOptions(),
            'currency' => $expense->company->base_currency_code,
        ]);
    }

    public function edit(Expense $expense): Response|RedirectResponse
    {
        $this->guardCompany($expense);

        if (! $expense->isEditable()) {
            return to_route('expenses.show', $expense)->with('error', 'Posted expenses can only be voided.');
        }

        $company = $expense->company;

        $expense->load('lines');

        return Inertia::render('expenses/edit', [
            'expense' => [
                'id' => $expense->id,
                'branch_id' => $expense->branch_id ? (string) $expense->branch_id : null,
                'contact_id' => $expense->contact_id,
                'expense_date' => $expense->expense_date->toDateString(),
                'reference_no' => $expense->reference_no,
                'payee' => $expense->payee,
                'description' => $expense->description,
                'notes' => $expense->notes,
                'lines' => $expense->lines->map(fn($line) => [
                    'account_id' => $line->account_id,
                    'particulars' => $line->particulars,
                    'quantity' => (string) ($line->quantity ?? 1),
                    'uom' => $line->uom ?? '',
                    'unit_price' => (string) ($line->unit_price ?? $line->amount),
                    'amount' => (string) $line->amount,
                    'tax_type' => $line->tax_type,
                    'withholding_tax_rate' => (string) $line->withholding_tax_rate,
                    'description' => $line->description ?? '',
                ]),
            ],
            'branches' => $this->branchOptions($company),
            'contacts' => $company->contacts()
                ->where('is_active', true)
                ->whereIn('type', ['vendor', 'both'])
                ->orderBy('name')
                ->get(['id', 'name', 'tax_identification_number', 'address', 'default_payment_terms_days']),
            'accountOptions' => $this->accountOptions($company->id),
            'withholdingRates' => $this->withholdingRates(),
            'vatRate' => (float) config('accounting.expenses.vat_rate', 0.12),
            'currency' => $company->base_currency_code,
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->guardCompany($expense);

        if (! $expense->isEditable()) {
            return to_route('expenses.show', $expense)->with('error', 'Only draft expenses can be changed.');
        }

        $header = collect($request->validated())->except(['post_now', 'lines'])->all();
        $linesInput = $request->validated('lines');

        DB::transaction(function () use ($expense, $header, $linesInput) {
            $expense->fill($header)->save();

            $expense->lines()->delete();

            foreach ($linesInput as $index => $line) {
                $expense->lines()->create([
                    'account_id' => $line['account_id'],
                    'particulars' => $line['particulars'],
                    'quantity' => $line['quantity'],
                    'uom' => $line['uom'] ?? null,
                    'unit_price' => $line['unit_price'],
                    'amount' => $line['amount'],
                    'tax_type' => $line['tax_type'],
                    'vat_amount' => $this->posting->computeLineVat((float) $line['amount'], $line['tax_type']),
                    'withholding_tax_rate' => $line['withholding_tax_rate'],
                    'withholding_tax_amount' => $this->posting->computeLineWithholding(
                        (float) $line['amount'],
                        (float) $line['withholding_tax_rate'],
                    ),
                    'description' => $line['description'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        });

        if ($request->boolean('post_now')) {
            $this->posting->post($expense, $request->user());

            return to_route('expenses.show', $expense)->with('success', 'Expense posted.');
        }

        return to_route('expenses.show', $expense)->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->guardCompany($expense);

        if (! $expense->isDraft()) {
            throw ValidationException::withMessages([
                'expense' => 'Only draft expenses can be deleted. Void a posted one instead.',
            ]);
        }

        $expense->delete();

        return to_route('expenses.index')->with('success', 'Draft expense removed.');
    }

    public function post(Request $request, Expense $expense): RedirectResponse
    {
        $this->guardCompany($expense);

        $this->posting->post($expense, $request->user());

        return to_route('expenses.show', $expense)->with('success', 'Expense posted.');
    }

    public function pay(Request $request, Expense $expense): RedirectResponse
    {
        $this->guardCompany($expense);

        $validated = $request->validate([
            'settlement_account' => [
                'required',
                Rule::in(array_keys((array) config('accounting.expenses.settlement_accounts'))),
            ],
            'payment_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'required_if:settlement_account,cheque', 'string', 'max:255'],
            'check_number' => ['nullable', 'required_if:settlement_account,cheque', 'string', 'max:50'],
        ]);

        $this->posting->pay(
            $expense,
            $validated['settlement_account'],
            $request->user(),
            $validated['payment_date'] ?? null,
            $validated['bank_name'] ?? null,
            $validated['check_number'] ?? null,
        );

        return to_route('expenses.show', $expense)->with('success', 'Expense marked as paid.');
    }

    public function void(Request $request, Expense $expense): RedirectResponse
    {
        $this->guardCompany($expense);

        $validated = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        $this->posting->void($expense, $validated['transaction_date'] ?? now(), $request->user());

        return to_route('expenses.show', $expense)->with('success', 'Expense voided.');
    }

    public function checkVoucher(Expense $expense)
    {
        $this->guardCompany($expense);

        if ($expense->settlement_account !== 'cheque') {
            abort(422, 'This expense was not paid by check.');
        }

        $expense->loadMissing('paymentJournalEntry.lines.account');

        $lines = $expense->paymentJournalEntry?->lines->map(fn($line) => [
            'account_name' => $line->account?->name,
            'debit_cents' => Money::cents($line->debit),
            'credit_cents' => Money::cents($line->credit),
        ]) ?? collect();

        $amountCents = Money::cents($expense->netPayable());

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.check-voucher', [
            'payee' => $expense->payee,
            'date' => $expense->paid_at?->toDateString() ?? now()->toDateString(),
            'particulars' => $expense->description ?: ($expense->payee ? "Expense — {$expense->payee}" : 'Expense'),
            'amount_cents' => $amountCents,
            'amount_words' => \App\Support\NumberToWords::pesos($amountCents),
            'lines' => $lines,
            'bank_name' => $expense->bank_name,
            'check_number' => $expense->check_number,
        ])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper([0, 0, 612, 396]);

        return $pdf->stream('check-voucher-' . $expense->id . '.pdf');
    }

    /**
     * Active branches with the account-code suffix each one uses in the COA.
     *
     * @return list<array{id: int, name: string, code: string, account_suffix: string|null}>
     */
    protected function branchOptions($company): array
    {
        return $company->branches()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'account_suffix' => config("accounting.branch_suffixes.{$branch->code}"),
            ])
            ->all();
    }

    /**
     * @return list<array{category: string, options: list<array{id: int, code: string, label: string}>}>
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
                    'code' => $account->code,
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
        return collect((array) config('accounting.expenses.withholding_tax_rates'))
            ->map(fn(string $label, string $rate) => ['value' => $rate, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function settlementOptions(): array
    {
        $labels = (array) config('accounting.expenses.settlement_labels');

        return collect((array) config('accounting.expenses.settlement_accounts'))
            ->keys()
            ->map(fn(string $key) => ['value' => $key, 'label' => $labels[$key] ?? $key])
            ->values()
            ->all();
    }

    protected function guardCompany(Expense $expense): void
    {
        abort_unless((int) $expense->company_id === (int) CurrentCompany::find()?->id, 404);
    }
}
