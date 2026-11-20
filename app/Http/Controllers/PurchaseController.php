<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Purchase;
use App\Services\PurchasePostingService;
use App\Support\BranchAccounts;
use App\Support\CurrentCompany;
use App\Support\Money;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchasePostingService $posting,
    ) {}

    public function index(Request $request): Response
    {
        $company = CurrentCompany::find();

        // One switch for settled and cancelled purchases: paid and voided are
        // hidden unless "Show paid & voided" is ticked.
        $includeClosed = filter_var($request->input('include_paid', false), FILTER_VALIDATE_BOOLEAN);

        $entries = $company->purchases()
            ->with([
                'branch:id,name,code',
                'contact:id,name,tax_identification_number,address',
                'lines.account:id,code,name',
                'journalEntry:id,reference_no,status',
            ])
            ->when(
                $request->filled('status'),
                fn($query) => $query->where('status', $request->input('status'))
            )
            ->unless(
                $includeClosed,
                fn($query) => $query
                    // whereNull keeps rows that have no payment status yet.
                    ->where(fn($q) => $q->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid'))
                    ->where('status', '!=', 'voided')
            )
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('purchases/index', [
            'entries' => $entries->through(
                fn(Purchase $purchase): array => [
                    'id' => $purchase->id,
                    'purchase_date' => $purchase->purchase_date->toDateString(),
                    'vendor_name' => $purchase->contact?->name
                        ?? $purchase->vendor_name,
                    'reference_no' => $purchase->reference_no,
                    'status' => $purchase->status,
                    'payment_status' => $purchase->payment_status,
                    'net_payable_cents' => Money::cents(
                        $purchase->netPayable()
                    ),
                    'branch' => $purchase->branch?->name,
                    // Chart of Accounts code(s) used by the purchase lines.
                    'account' => $purchase->lines
                        ->map(fn($line) => $line->account?->code)
                        ->filter()
                        ->unique()
                        ->values()
                        ->implode(', '),

                    'journal_entry' => $purchase->journalEntry
                        ? [
                            'id' => $purchase->journalEntry->id,
                            'reference_no' => $purchase->journalEntry->reference_no,
                        ]
                        : null,
                ]
            )->all(),

            'filters' => [
                'include_paid' => $includeClosed,
            ],

            'currency' => $company->base_currency_code,
        ]);
    }


    public function create(): Response
    {
        $company = CurrentCompany::find();
        // dd([
        //     'branches' => $this->branchOptions($company),
        //     'accountOptions' => $this->accountOptions($company->id),
        //     'withholdingRates' => $this->withholdingRates(),
        //     'vatRate' => (float) config('accounting.purchases.vat_rate', 0.12),
        //     'contacts' => $company->contacts()
        //         ->where('is_active', true)
        //         ->whereIn('type', ['vendor', 'both'])
        //         ->orderBy('name')
        //         ->get(['id', 'name', 'tax_identification_number', 'address', 'default_payment_terms_days']),
        //     'currency' => $company->base_currency_code,
        //     'defaults' => [
        //         'purchase_date' => now()->toDateString(),
        //     ],
        // ]);
        return Inertia::render('purchases/create', [
            'branches' => $this->branchOptions($company),
            'accountOptions' => $this->accountOptions($company->id),
            'withholdingRates' => $this->withholdingRates(),
            'vatRate' => (float) config('accounting.purchases.vat_rate', 0.12),
            'contacts' => $company->contacts()
                ->where('is_active', true)
                ->whereIn('type', ['vendor', 'both'])
                ->orderBy('name')
                ->get(['id', 'name', 'tax_identification_number', 'address', 'default_payment_terms_days']),
            'currency' => $company->base_currency_code,
            'defaults' => [
                'purchase_date' => now()->toDateString(),
            ],
        ]);
    }


    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $header = collect($request->validated())->except(['post_now', 'lines'])->all();
        $linesInput = $request->validated('lines');

        $purchase = DB::transaction(function () use ($company, $header, $linesInput, $request) {
            $purchase = $company->purchases()->create([
                ...$header,
                'status' => 'draft',
                'created_by' => $request->user()?->id,
            ]);

            foreach ($linesInput as $index => $line) {
                $purchase->lines()->create([
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

            return $purchase;
        });

        if ($request->boolean('post_now')) {
            return to_route('purchases.show', $purchase)->with('success', 'Purchase saved. Select the credit account in the journal summary before posting.');
        }

        return to_route('purchases.show', $purchase)->with('success', 'Purchase saved as a draft.');
    }

    public function show(Purchase $purchase): Response
    {
        $this->guardCompany($purchase);

        $purchase->load([
            'branch:id,name,code',
            'debitAccount:id,code,name',
            'payableAccount:id,code,name',
            'journalEntry.lines.account:id,code,name',
              'intercompanyJournalEntry.lines.account:id,code,name',
            'clearingJournalEntry.lines.account:id,code,name',
            'paymentJournalEntry.lines.account:id,code,name',
            'creator:id,name',
            'poster:id,name',
            'payer:id,name',
            'lines.account:id,code,name',
            'contact:id,name'
        ]);

        $paymentConfig = (array) config('accounting.purchases');
        $accountDetails = function (string|array|null $mapping) use ($purchase): array {
            try {
                $code = $purchase->branch
                    ? BranchAccounts::resolve($mapping, $purchase->branch)
                    : null;
            } catch (DomainException) {
                $code = null;
            }

            $account = $code
                ? Account::query()
                    ->where('company_id', $purchase->company_id)
                    ->where('code', $code)
                    ->first()
                : null;

            return [
                'account_code' => $account?->code,
                'account_name' => $account?->name ?? 'Account mapping required',
            ];
        };

        try {
            $affiliateAccountCode = $purchase->branch
                ? BranchAccounts::resolve($paymentConfig['affiliate_due_to_account'] ?? null, $purchase->branch)
                : null;
        } catch (DomainException) {
            $affiliateAccountCode = null;
        }

        $draftJournalLines = null;
        if ($purchase->isDraft()) {
            $draftJournalLines = $purchase->lines
                ->groupBy('account_id')
                ->map(function ($lines) {
                    $account = $lines->first()->account;

                    return [
                        'account_code' => $account?->code,
                        'account_name' => $account?->name,
                        'debit_cents' => Money::sum($lines->pluck('amount')),
                        'credit_cents' => 0,
                    ];
                })->values();

            $vatCents = Money::sum($purchase->lines->pluck('vat_amount'));
            if ($vatCents > 0) {
                $draftJournalLines->push([
                    ...$accountDetails($paymentConfig['input_vat_account'] ?? null),
                    'debit_cents' => $vatCents,
                    'credit_cents' => 0,
                ]);
            }

            $withholdingCents = Money::sum($purchase->lines->pluck('withholding_tax_amount'));
            if ($withholdingCents > 0) {
                $draftJournalLines->push([
                    ...$accountDetails($paymentConfig['withholding_tax_payable_account'] ?? null),
                    'debit_cents' => 0,
                    'credit_cents' => $withholdingCents,
                ]);
            }

            $draftJournalLines->push([
                'account_code' => null,
                'account_name' => 'Select credit account below',
                'debit_cents' => 0,
                'credit_cents' => Money::cents($purchase->netPayable()),
            ]);
        }

        $clearingJournalEntry = null;
        $paymentJournalEntry = null;

        if ($purchase->clearingJournalEntry) {
            $amount = Money::cents($purchase->netPayable());
            $payable = $purchase->payableAccount
                ? ['account_code' => $purchase->payableAccount->code, 'account_name' => $purchase->payableAccount->name]
                : $accountDetails($paymentConfig['payable_account'] ?? null);
            $clearing = $accountDetails($paymentConfig['clearing_account'] ?? null);
            $settlementMapping = $paymentConfig['settlement_accounts'][$purchase->settlement_account] ?? null;
            $settlement = $accountDetails($settlementMapping);

            $clearingJournalEntry = [
                'id' => $purchase->clearingJournalEntry->id,
                'reference_no' => $purchase->clearingJournalEntry->reference_no,
                'is_allocation' => true,
                'lines' => [
                    [...$payable, 'debit_cents' => $amount, 'credit_cents' => 0],
                    [...$clearing, 'debit_cents' => 0, 'credit_cents' => $amount],
                ],
            ];

            $paymentJournalEntry = $purchase->paymentJournalEntry ? [
                'id' => $purchase->paymentJournalEntry->id,
                'reference_no' => $purchase->paymentJournalEntry->reference_no,
                'is_allocation' => true,
                'lines' => [
                    [...$clearing, 'debit_cents' => $amount, 'credit_cents' => 0],
                    [...$settlement, 'debit_cents' => 0, 'credit_cents' => $amount],
                ],
            ] : null;
        } elseif ($purchase->paymentJournalEntry) {
            $paymentJournalEntry = [
                'id' => $purchase->paymentJournalEntry->id,
                'reference_no' => $purchase->paymentJournalEntry->reference_no,
                'is_allocation' => false,
                'lines' => $purchase->paymentJournalEntry->lines->map(fn($line) => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit_cents' => Money::cents($line->debit),
                    'credit_cents' => Money::cents($line->credit),
                ]),
            ];
        }

        return Inertia::render('purchases/show', [
            'purchase' => [
                'id' => $purchase->id,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'reference_no' => $purchase->reference_no,

                'vendor_name' => $purchase->contact?->name ?? $purchase->vendor_name,

                'contact' => $purchase->contact
                    ? [
                        'id' => $purchase->contact->id,
                        'name' => $purchase->contact->name,
                    ]
                    : null,

                'taxable_amount_cents' => Money::cents($purchase->taxable_amount),
                'vat_amount_cents' => Money::cents($purchase->vat_amount),
                'withholding_tax_rate' => (float) $purchase->withholding_tax_rate,
                'withholding_tax_amount_cents' => Money::cents(
                    $purchase->withholding_tax_amount
                ),
                'gross_amount_cents' => Money::cents($purchase->grossAmount()),
                'net_payable_cents' => Money::cents($purchase->netPayable()),
                'tax_type' => $purchase->tax_type,
                'payment_status' => $purchase->payment_status,
                'is_affiliate_settlement' => $purchase->isAffiliateSettlement(),
                'settlement_account' => $purchase->settlement_account,
                'notes' => $purchase->notes,
                'status' => $purchase->status,

                'branch' => $purchase->branch?->name,

                'account' => $purchase->debitAccount
                    ? [
                        'code' => $purchase->debitAccount->code,
                        'name' => $purchase->debitAccount->name,
                    ]
                    : null,

                'created_by' => $purchase->creator?->name,
                'posted_by' => $purchase->poster?->name,
                'posted_at' => $purchase->posted_at?->toDateTimeString(),
                'paid_by' => $purchase->payer?->name,
                'paid_at' => $purchase->paid_at?->toDateTimeString(),

                'lines' => $purchase->lines->map(fn($line) => [
                    'id' => $line->id,
                    'particulars' => $line->particulars,
                    'quantity' => (float) $line->quantity,
                    'uom' => $line->uom,
                    'unit_price' => (float) $line->unit_price,
                    'amount_cents' => Money::cents($line->amount),
                    'account' => $line->account
                        ? [
                            'code' => $line->account->code,
                            'name' => $line->account->name,
                        ]
                        : null,
                ])->values(),
            ],
            'journalEntry' => $purchase->journalEntry ? [
                'id' => $purchase->journalEntry->id,
                'reference_no' => $purchase->journalEntry->reference_no,
                'status' => $purchase->journalEntry->status,
                'lines' => $purchase->journalEntry->lines->map(fn($line) => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit_cents' => Money::cents($line->debit),
                    'credit_cents' => Money::cents($line->credit),
                ]),
            ] : null,
            'draftJournalLines' => $draftJournalLines?->all(),
            'intercompanyJournalEntry' => $purchase->intercompanyJournalEntry ? [
                'id' => $purchase->intercompanyJournalEntry->id,
                'reference_no' => $purchase->intercompanyJournalEntry->reference_no,
                'status' => $purchase->intercompanyJournalEntry->status,
                'lines' => $purchase->intercompanyJournalEntry->lines->map(fn ($line) => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit_cents' => Money::cents($line->debit),
                    'credit_cents' => Money::cents($line->credit),
                ]),
            ] : null,
            'intercompanyVoucher' => \App\Models\IntercompanyVoucher::query()
                ->where('source_type', 'purchase')->where('source_id', $purchase->id)
                ->where('company_id', $purchase->company_id)->first(['id', 'status']),
            'intercompanyAccounts' => $purchase->status === 'posted' && ! $purchase->intercompanyJournalEntry
                ? app(\App\Services\IntercompanyJournalService::class)->accountOptions($purchase->company)
                : [],
            'intercompanyBranches' => $purchase->status === 'posted' && ! $purchase->intercompanyJournalEntry
                ? app(\App\Services\IntercompanyJournalService::class)->branchOptions($purchase->company)
                : [],
            'payableAccountOptions' => $purchase->isDraft() ? $this->payableAccountOptions($purchase) : [],
            'selectedPayableAccountId' => $purchase->payable_account_id,
            'affiliateAccountCode' => $affiliateAccountCode,
            'paymentJournalEntry' => $paymentJournalEntry,
            'clearingJournalEntry' => $clearingJournalEntry,
            'actions' => [
                'can_edit' => $purchase->isEditable(),
                'can_post' => $purchase->isDraft(),
                'can_void' => ! $purchase->isVoided(),
                'can_delete' => $purchase->isDraft(),
                'can_mark_paid' => $purchase->canMarkPaid(),
                'can_mark_for_voucher' => $purchase->canMarkPaid() && $purchase->payment_status === 'unpaid',
            ],
            'settlementOptions' => $this->settlementOptions(),
            'currency' => $purchase->company->base_currency_code,
        ]);
    }

    public function edit(Purchase $purchase): Response|RedirectResponse
    {
        $this->guardCompany($purchase);

        if (! $purchase->isEditable()) {
            return to_route('purchases.show', $purchase)->with('error', 'Posted purchases can only be voided.');
        }

        $company = $purchase->company;

        $purchase->load('lines');

        return Inertia::render('purchases/edit', [
            'purchase' => [
                'id' => $purchase->id,
                'branch_id' => $purchase->branch_id,
                'contact_id' => $purchase->contact_id,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'due_date' => $purchase->due_date?->toDateString(),
                'debit_account_id' => $purchase->debit_account_id,
                'reference_no' => $purchase->reference_no,
                'vendor_name' => $purchase->vendor_name,
                'description' => $purchase->description,
                'taxable_amount' => (float) $purchase->taxable_amount,
                'tax_type' => $purchase->tax_type,
                'withholding_tax_rate' => (float) $purchase->withholding_tax_rate,
                'notes' => $purchase->notes,
                'lines' => $purchase->lines->map(fn($line) => [
                    'account_id' => $line->account_id,
                    'particulars' => $line->particulars,
                    'quantity' => (string) $line->quantity,
                    'uom' => $line->uom ?? '',
                    'unit_price' => (string) (float) $line->unit_price,
                    'amount' => (string) $line->amount,
                    'tax_type' => $line->tax_type,
                    'withholding_tax_rate' => (string) $line->withholding_tax_rate,
                    'description' => $line->description ?? '',
                ])->values(),
            ],
            'contacts' => $this->contactOptions($company->id),
            'branches' => $this->branchOptions($company),
            'accountOptions' => $this->accountOptions($company->id),
            'withholdingRates' => $this->withholdingRates(),
            'vatRate' => (float) config('accounting.purchases.vat_rate', 0.12),
            'currency' => $company->base_currency_code,
        ]);
    }


    public function update(UpdatePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        if (! $purchase->isEditable()) {
            return to_route('purchases.show', $purchase)->with('error', 'Only draft purchases can be changed.');
        }

        $header = collect($request->validated())->except(['post_now', 'lines'])->all();
        $linesInput = $request->validated('lines');

        DB::transaction(function () use ($purchase, $header, $linesInput) {
            $purchase->fill($header)->save();

            $purchase->lines()->delete();

            foreach ($linesInput as $index => $line) {
                $purchase->lines()->create([
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
            return to_route('purchases.show', $purchase)->with('success', 'Purchase updated. Select the credit account in the journal summary before posting.');
        }

        return to_route('purchases.show', $purchase)->with('success', 'Purchase updated.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->guardCompany($purchase);

        if (! $purchase->isDraft()) {
            throw ValidationException::withMessages([
                'purchase' => 'Only draft purchases can be deleted. Void a posted one instead.',
            ]);
        }

        $purchase->delete();

        return to_route('purchases.index')->with('success', 'Draft purchase removed.');
    }

    public function post(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->guardCompany($purchase);

        abort_unless($purchase->isDraft(), 422, 'Only draft purchases can be posted.');

        $validated = $request->validate([
            'payable_account_id' => ['required', 'integer'],
        ]);

        $account = Account::query()
            ->whereKey($validated['payable_account_id'])
            ->whereIn('id', collect($this->payableAccountOptions($purchase))->pluck('id'))
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'payable_account_id' => 'Select an active liability account for this branch.',
            ]);
        }

        DB::transaction(function () use ($purchase, $account, $request) {
            $purchase->payable_account_id = $account->id;
            $purchase->save();
            $this->posting->post($purchase, $request->user());
        });

        return to_route('purchases.show', $purchase)->with('success', 'Purchase posted.');
    }

    public function pay(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->guardCompany($purchase);

        $validated = $request->validate([
            'settlement_account' => [
                'required',
                Rule::in(array_keys((array) config('accounting.purchases.settlement_accounts'))),
            ],
            'payment_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'required_if:settlement_account,cheque', 'string', 'max:255'],
            'check_number' => ['nullable', 'required_if:settlement_account,cheque', 'string', 'max:50'],
        ]);

        $this->posting->pay(
            $purchase,
            $validated['settlement_account'],
            $request->user(),
            $validated['payment_date'] ?? null,
            $validated['bank_name'] ?? null,
            $validated['check_number'] ?? null,
        );

        return to_route('purchases.show', $purchase)->with('success', 'Purchase marked as paid.');
    }

    public function void(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->guardCompany($purchase);

        $validated = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        $this->posting->void($purchase, $validated['transaction_date'] ?? now(), $request->user());

        return to_route('purchases.show', $purchase)->with('success', 'Purchase voided.');
    }

    public function outgoing(Request $request): Response
    {
        $company = CurrentCompany::find();

        // Purchases enter this workflow only after being marked For Voucher.
        // Keep them visible while awaiting a cheque or release.
        $purchases = $company->purchases()
            ->where('status', 'posted')
            ->whereIn('payment_status', ['for_check', 'for_release'])
            ->with([
                'branch:id,name,code',
                'contact:id,name,tax_identification_number,address',
                'journalEntry:id,reference_no,status',
                'payableAccount:id,code,name',
            ])
            ->orderBy('due_date')
            ->orderByDesc('purchase_date')
            ->get()
            ->reject(fn (Purchase $purchase) => $purchase->isAffiliateSettlement());

        $payableConfig = config('accounting.purchases.payable_account');
        $clearingConfig = config('accounting.purchases.clearing_account');
        $settlementConfig = (array) config('accounting.purchases.settlement_accounts');

        $resolveCode = function ($config, $branch): ?string {
            if (! $branch) {
                return null;
            }

            try {
                return BranchAccounts::resolve($config, $branch);
            } catch (DomainException) {
                return null; // branch code has no suffix mapping
            }
        };

        // Resolve codes per purchase first, then look up all names in one query.
        $resolved = $purchases->mapWithKeys(fn(Purchase $purchase) => [
            $purchase->id => [
                'payable' => $purchase->payableAccount?->code ?? $resolveCode($payableConfig, $purchase->branch),
                'clearing' => $resolveCode($clearingConfig, $purchase->branch),
                'settlement' => collect($settlementConfig)
                    ->map(fn($config) => $resolveCode($config, $purchase->branch))
                    ->all(),
            ],
        ]);

        $intercompanyVouchers = \App\Models\IntercompanyVoucher::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['for_voucher', 'send_for_cheque', 'for_release'])
            ->whereHas('intercompanyJournalEntry', fn ($query) => $query->where('status', 'posted'))
            ->with(['branch:id,name,code', 'clearingAccount:id,code,name', 'intercompanyJournalEntry:id,reference_no,status'])
            ->orderBy('created_at')
            ->get();

        $voucherSettlements = $intercompanyVouchers->mapWithKeys(fn ($voucher) => [
            $voucher->id => collect($settlementConfig)
                ->map(fn ($config) => $resolveCode($config, $voucher->branch))
                ->all(),
        ]);

        $codes = $resolved
            ->flatMap(fn(array $r) => [$r['payable'], $r['clearing'], ...array_values($r['settlement'])])
            ->merge($voucherSettlements->flatMap(fn (array $accounts) => array_values($accounts)))
            ->filter()
            ->unique()
            ->values();

        $labels = Account::query()
            ->where('company_id', $company->id)
            ->whereIn('code', $codes)
            ->get()
            ->mapWithKeys(fn(Account $a) => [$a->code => "{$a->code} · {$a->name}"]);

        $label = fn(?string $code): ?string => $code ? ($labels[$code] ?? null) : null;

        return Inertia::render('outgoing/Index', [
            'intercompanyVouchers' => $intercompanyVouchers
                ->map(fn ($voucher) => [
                    'id' => $voucher->id,
                    'source_type' => $voucher->source_type,
                    'source_id' => $voucher->source_id,
                    'source_reference' => $voucher->source_reference,
                    'journal_reference' => $voucher->intercompanyJournalEntry?->reference_no,
                    'payee' => $voucher->payee,
                    'branch' => $voucher->branch?->name,
                    'clearing_account' => $voucher->clearingAccount?->code.' · '.$voucher->clearingAccount?->name,
                    'settlement_accounts' => collect($voucherSettlements[$voucher->id])
                        ->map(fn (?string $code) => $label($code))
                        ->all(),
                    'bank_name' => $voucher->bank_name,
                    'check_number' => $voucher->check_number,
                    'settlement_account' => $voucher->settlement_account,
                    'processing_status' => match ($voucher->status) {
                        'for_voucher' => 'for_voucher',
                        'send_for_cheque' => $voucher->check_number ? 'for_release' : 'for_cheque',
                        'for_release' => 'for_release',
                        'paid' => 'released',
                        default => 'pending',
                    },
                    'amount_cents' => Money::cents($voucher->amount),
                ])->values()->all(),
            'entries' => $purchases->map(
                fn(Purchase $purchase): array => [
                    'id' => $purchase->id,
                    'purchase_date' => $purchase->purchase_date->toDateString(),
                    'due_date' => $purchase->due_date?->toDateString(),
                    'vendor_name' => $purchase->contact?->name ?? $purchase->vendor_name,
                    'reference_no' => $purchase->reference_no,
                    'net_payable_cents' => Money::cents($purchase->netPayable()),
                    'branch' => $purchase->branch?->name,
                    'payable_account' => $label($resolved[$purchase->id]['payable']),
                    'clearing_account' => $label($resolved[$purchase->id]['clearing']),
                    'settlement_accounts' => collect($resolved[$purchase->id]['settlement'])
                        ->map(fn(?string $code) => $label($code))
                        ->all(),
                    'bank_name' => $purchase->bank_name,
                    'check_number' => $purchase->check_number,
                    'settlement_account' => $purchase->settlement_account,
                    'processing_status' => match (true) {
                        $purchase->status === 'voided' => 'cancelled',
                        $purchase->payment_status === 'paid' => 'released',
                        $purchase->payment_status === 'for_release' => 'for_release',
                        $purchase->payment_status === 'for_check' && $purchase->check_number => 'for_release',
                        $purchase->payment_status === 'for_check' => 'for_cheque',
                        default => 'pending',
                    },
                ]
            )->values()->all(),
            'settlementOptions' => $this->settlementOptions(),
            'currency' => $company->base_currency_code,
        ]);
    }

    public function updateProcessingStatus(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->guardCompany($purchase);

        abort_unless($purchase->canMarkPaid(), 422, 'This purchase is not awaiting payment.');

        $validated = $request->validate([
            'processing_status' => ['required', Rule::in(['pending', 'for_check'])],
        ]);

        $purchase->update([
            'payment_status' => $validated['processing_status'] === 'for_check' ? 'for_check' : 'unpaid',
        ]);

        return back()->with('success', 'Status updated.');
    }

    public function payBatch(Request $request): RedirectResponse
    {
        $company = CurrentCompany::find();
        $sources = array_keys((array) config('accounting.purchases.settlement_accounts'));

        $data = $request->validate([
            'purchase_ids' => ['required', 'array', 'min:1'],
            'purchase_ids.*' => ['integer'],
            'settlement_account' => ['required', Rule::in($sources)],
            'payment_date' => ['nullable', 'date'],
        ]);

        $ids = array_unique($data['purchase_ids']);

        $purchases = $company->purchases()->with('branch')->whereIn('id', $ids)->get();

        if ($purchases->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'purchase_ids' => 'Some of the selected purchases could not be found.',
            ]);
        }

        foreach ($purchases as $purchase) {
            if ($purchase->payment_status === 'for_check' && $purchase->check_number) {
                throw ValidationException::withMessages([
                    'status' => "Purchase #{$purchase->id} already has a cheque number. Release it from its status menu instead.",
                ]);
            }
        }

        // Skip rows that are already For Cheque; those are processed on release.
        if ($data['settlement_account'] === 'cheque') {
            DB::transaction(function () use ($purchases) {
                foreach ($purchases as $purchase) {
                    $forCheque = $purchase->payment_status === 'for_check';

                    if (! $forCheque) {
                        $this->posting->sendForCheque($purchase);
                    }
                }
            });
        } else {
            $this->posting->markBatchForRelease(
                $purchases->all(),
                $data['settlement_account'],
            );
        }

        return back()->with(
            'success',
            $data['settlement_account'] === 'cheque'
                ? 'Purchases sent for cheque.'
                : 'Selected purchases marked For Release.',
        );
    }

    public function forCheque(Purchase $purchase): RedirectResponse
    {
        $this->posting->sendForCheque($this->owned($purchase));

        return back()->with('success', 'Purchase sent for cheque.');
    }


    public function chequeDetails(Request $request, Purchase $purchase): RedirectResponse
    {
        $purchase = $this->owned($purchase);

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'check_number' => [
                'required',
                'string',
                'max:50',
            ],
        ]);

        $this->posting->setChequeDetails($purchase, $data['bank_name'], $data['check_number']);

        return back()->with('success', 'Cheque saved. The purchase is now For Release.');
    }


    public function release(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate(['payment_date' => ['nullable', 'date']]);

        $this->posting->release(
            $this->owned($purchase)->load('branch'),
            $request->user(),
            $data['payment_date'] ?? null,
        );

        return back()->with('success', 'Payment released and posted.');
    }

    public function cancelPayment(Purchase $purchase): RedirectResponse
    {
        $this->posting->cancelPayment($this->owned($purchase));

        return back()->with('success', 'Payment cancelled. The purchase is back to Pending.');
    }

    protected function owned(Purchase $purchase): Purchase
    {
        abort_unless($purchase->company_id === CurrentCompany::find()->id, 404);

        return $purchase;
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

    /** @return list<array{id: int, code: string, name: string, label: string}> */
    protected function payableAccountOptions(Purchase $purchase): array
    {
        $purchase->loadMissing('branch');
        if (! $purchase->branch) {
            return [];
        }

        try {
            $suffix = $purchase->branch->accountSuffix();
        } catch (DomainException) {
            return [];
        }

        return Account::query()
            ->where('company_id', $purchase->company_id)
            ->where('is_active', true)
            ->whereHas('accountType', fn ($query) => $query->where('category', 'liability'))
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get()
            ->filter(fn (Account $account) => ! str_contains($account->code, '-') || str_ends_with($account->code, '-'.$suffix))
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => $account->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function withholdingRates(): array
    {
        return collect((array) config('accounting.purchases.withholding_tax_rates'))
            ->map(fn(string $label, string $rate) => ['value' => $rate, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function settlementOptions(): array
    {
        $labels = (array) config('accounting.purchases.settlement_labels');

        return collect((array) config('accounting.purchases.settlement_accounts'))
            ->keys()
            ->map(fn(string $key) => ['value' => $key, 'label' => $labels[$key] ?? $key])
            ->values()
            ->all();
    }

    protected function guardCompany(Purchase $purchase): void
    {
        abort_unless($purchase->company_id === CurrentCompany::find()->id, 404);
    }


    public function checkVoucher(Purchase $purchase)
    {
        $this->guardCompany($purchase);

        $purchase->loadMissing('paymentJournalEntry.lines.account');

        $lines = $purchase->paymentJournalEntry?->lines->map(fn($line) => [
            'account_name' => $line->account?->name,
            'debit_cents' => Money::cents($line->debit),
            'credit_cents' => Money::cents($line->credit),
        ]) ?? collect();

        $amountCents = Money::cents($purchase->netPayable());

        $pdf = Pdf::loadView('pdf.check-voucher', [
            'payee' => $purchase->vendorDisplayName(),

            // Use paid_at when available; otherwise use the purchase date.
            'date' => $purchase->paid_at?->toDateString()
                ?? $purchase->purchase_date?->toDateString()
                ?? $purchase->created_at?->toDateString()
                ?? now()->toDateString(),

            'particulars' => $purchase->description
                ?: 'Purchase — ' . $purchase->vendorDisplayName(),

            'amount_cents' => $amountCents,
            'amount_words' => NumberToWords::pesos($amountCents),

            // Empty collection if there is no payment JE yet.
            'lines' => $lines,

            'bank_name' => $purchase->bank_name,
            'check_number' => $purchase->check_number,
        ])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper([0, 0, 612, 396]);

        return $pdf->stream('check-voucher-' . $purchase->id . '.pdf');
    }

    public function batchCheckVoucher(Request $request)
    {
        $company = CurrentCompany::find();
        $validated = $request->validate([
            'purchase_ids' => ['nullable', 'array'],
            'purchase_ids.*' => ['integer', 'distinct'],
            'voucher_ids' => ['nullable', 'array'],
            'voucher_ids.*' => ['integer', 'distinct'],
            'settlement_account' => ['nullable', Rule::in([...array_keys((array) config('accounting.purchases.settlement_accounts')), 'cheque'])],
            'payment_date' => ['nullable', 'date'],
        ]);

        $purchaseIds = $validated['purchase_ids'] ?? [];
        $voucherIds = $validated['voucher_ids'] ?? [];

        // Intercompany vouchers print the same overlay as purchases, so a
        // selection of either kind produces one cheque voucher.
        if ($voucherIds !== [] && $purchaseIds === []) {
            return $this->batchVoucherCheckVoucher($company, $voucherIds, $validated);
        }

        if ($purchaseIds === []) {
            throw ValidationException::withMessages([
                'purchase_ids' => 'Select at least one purchase or intercompany voucher.',
            ]);
        }

        $purchases = $company->purchases()
            ->whereIn('id', $purchaseIds)
            ->with(['branch', 'contact'])
            ->get();

        if ($purchases->count() !== count($purchaseIds)) {
            throw ValidationException::withMessages([
                'purchase_ids' => 'Some selected purchases could not be found.',
            ]);
        }

        if ($purchases->contains(fn(Purchase $purchase) => $purchase->status !== 'posted' || ! $purchase->canMarkPaid())) {
            throw ValidationException::withMessages([
                'purchase_ids' => 'Only posted purchases awaiting payment can be included in a cheque voucher.',
            ]);
        }

        if ($purchases->pluck('branch_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'purchase_ids' => 'A cheque voucher can include purchases from only one branch.',
            ]);
        }

        $purchase = $purchases->first();
        if (! $purchase?->branch) {
            throw ValidationException::withMessages([
                'purchase_ids' => 'Assign a branch to the selected purchases before creating a cheque voucher.',
            ]);
        }

        $payees = $purchases
            ->map(fn(Purchase $purchase) => mb_strtolower(trim($purchase->vendorDisplayName() ?? '')))
            ->unique();

        if ($payees->count() !== 1 || $payees->first() === '') {
            throw ValidationException::withMessages([
                'purchase_ids' => 'A cheque voucher can include purchases for only one payee.',
            ]);
        }

        $accounting = (array) config('accounting.purchases');
        $source = $validated['settlement_account'] ?? 'bank_transfer';
        if ($source === 'cheque') {
            $source = 'bank_transfer';
        }

        try {
            $clearing = BranchAccounts::account($accounting['clearing_account'] ?? null, $purchase->branch);
            $settlement = BranchAccounts::account($accounting['settlement_accounts'][$source], $purchase->branch);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['settlement_account' => $exception->getMessage()]);
        }

        $amountCents = $purchases->sum(fn(Purchase $item) => Money::cents($item->netPayable()));
        $references = $purchases->map(fn(Purchase $item) => $item->reference_no ?: "Purchase #{$item->id}")->implode(', ');
        $date = isset($validated['payment_date'])
            ? Carbon::parse($validated['payment_date'])->toDateString()
            : now()->toDateString();
        $accounts = [
            ['name' => $clearing->name, 'debit_cents' => $amountCents, 'credit_cents' => 0],
            ['name' => $settlement->name, 'debit_cents' => 0, 'credit_cents' => $amountCents],
        ];

        $pdf = Pdf::loadView('pdf.batch-check-voucher-overlay', [
            'payee' => $purchase->vendorDisplayName(),
            'date' => $date,
            'particulars' => 'Payment for ' . $references,
            'amount_cents' => $amountCents,
            'amount_words' => NumberToWords::pesos($amountCents),
            'check_number' => $purchases->pluck('check_number')->filter()->unique()->count() === 1
                ? $purchases->pluck('check_number')->filter()->first()
                : null,
            'accounts' => $accounts,
        ])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper([0, 0, 612, 360]);

        return $pdf->stream('check-voucher-batch-' . $purchases->modelKeys()[0] . '.pdf');
    }

    /** @param list<int> $voucherIds */
    protected function batchVoucherCheckVoucher($company, array $voucherIds, array $validated)
    {
        $vouchers = \App\Models\IntercompanyVoucher::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $voucherIds)
            ->with(['branch', 'clearingAccount', 'intercompanyJournalEntry:id,status'])
            ->get();

        if ($vouchers->count() !== count($voucherIds)) {
            throw ValidationException::withMessages([
                'voucher_ids' => 'Some selected intercompany vouchers could not be found.',
            ]);
        }

        if ($vouchers->contains(fn ($voucher) => $voucher->intercompanyJournalEntry?->status !== 'posted')) {
            throw ValidationException::withMessages([
                'voucher_ids' => 'Only vouchers with a posted intercompany entry can be included in a cheque voucher.',
            ]);
        }

        // Only vouchers awaiting payment (not yet paid or cancelled) can generate a cheque voucher.
        $validVoucherStatuses = ['for_voucher', 'send_for_cheque', 'for_release'];
        if ($vouchers->contains(fn ($voucher) => !in_array($voucher->status, $validVoucherStatuses, true))) {
            throw ValidationException::withMessages([
                'voucher_ids' => 'Only vouchers awaiting payment can be included in a cheque voucher.',
            ]);
        }

        if ($vouchers->pluck('branch_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'voucher_ids' => 'A cheque voucher can include vouchers from only one branch.',
            ]);
        }

        $voucher = $vouchers->first();
        if (! $voucher?->branch) {
            throw ValidationException::withMessages([
                'voucher_ids' => 'Assign a branch to the selected vouchers before creating a cheque voucher.',
            ]);
        }

        $payees = $vouchers
            ->map(fn ($voucher) => mb_strtolower(trim($voucher->payee ?? '')))
            ->unique();

        if ($payees->count() !== 1 || $payees->first() === '') {
            throw ValidationException::withMessages([
                'voucher_ids' => 'A cheque voucher can include vouchers for only one payee.',
            ]);
        }

        $accounting = (array) config('accounting.purchases');
        $source = $validated['settlement_account'] ?? 'bank_transfer';
        if ($source === 'cheque') {
            $source = 'bank_transfer';
        }

        try {
            $settlement = BranchAccounts::account($accounting['settlement_accounts'][$source], $voucher->branch);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['settlement_account' => $exception->getMessage()]);
        }

        $amountCents = $vouchers->sum(fn ($item) => Money::cents($item->amount));
        $references = $vouchers
            ->map(fn ($item) => $item->source_reference ?: ucfirst($item->source_type).' #'.$item->source_id)
            ->implode(', ');
        $date = isset($validated['payment_date'])
            ? Carbon::parse($validated['payment_date'])->toDateString()
            : now()->toDateString();
        $accounts = [
            ['name' => $voucher->clearingAccount->name, 'debit_cents' => $amountCents, 'credit_cents' => 0],
            ['name' => $settlement->name, 'debit_cents' => 0, 'credit_cents' => $amountCents],
        ];

        $pdf = Pdf::loadView('pdf.batch-check-voucher-overlay', [
            'payee' => $voucher->payee,
            'date' => $date,
            'particulars' => 'Payment for ' . $references,
            'amount_cents' => $amountCents,
            'amount_words' => NumberToWords::pesos($amountCents),
            'check_number' => $vouchers->pluck('check_number')->filter()->unique()->count() === 1
                ? $vouchers->pluck('check_number')->filter()->first()
                : null,
            'accounts' => $accounts,
        ])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper([0, 0, 612, 360]);

        return $pdf->stream('check-voucher-batch-' . $voucher->id . '.pdf');
    }


    protected function contactOptions(int $companyId): array
    {
        return Contact::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'tax_identification_number',
                'address',
                'default_payment_terms_days',
            ])
            ->all();
    }

    protected function branchOptions(object $company): array
    {
        return $company->branches()
            ->where('is_active', true)
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
}
