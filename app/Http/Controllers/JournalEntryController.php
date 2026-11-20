<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\JETraits;
use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Services\FiscalYearService;
use App\Services\JournalEntryService;
use App\Support\CurrentCompany;
use App\Support\InertiaLinks;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;


class JournalEntryController extends Controller
{
    use JETraits;

    public function __construct(
        protected JournalEntryService $journalEntries,
        protected FiscalYearService $fiscalYears,
    ) {}

    public function index(Request $request): Response
    {
        $company = CurrentCompany::find();

        $filters = $request->only(['status', 'source_type', 'branch_id', 'period_id', 'from', 'to', 'q']);

        $entries = $company->journalEntries()
            ->with(['branch:id,name', 'accountingPeriod:id,name,status', 'posEntry:id,journal_entry_id,pos_reference'])
            ->withSum('lines', 'debit')
            ->withSum('lines', 'credit')
            ->withCount('lines')
            ->when($filters['status'] ?? null, fn($query, $value) => $query->displayStatus($value))
            ->when($filters['source_type'] ?? null, fn($query, $value) => $query->where('source_type', $value))
            ->when($filters['branch_id'] ?? null, fn($query, $value) => $query->where('branch_id', $value))
            ->when($filters['period_id'] ?? null, fn($query, $value) => $query->where('accounting_period_id', $value))
            ->when($filters['from'] ?? null, fn($query, $value) => $query->whereDate('transaction_date', '>=', $value))
            ->when($filters['to'] ?? null, fn($query, $value) => $query->whereDate('transaction_date', '<=', $value))
            ->when($filters['q'] ?? null, function ($query) use ($filters): void {
                $term = (string) $filters['q'];
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('reference_no', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('source_reference', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Vendor / cheque number / released flag for the entries on this page.
        $sources = $this->entrySources($company, $entries->getCollection()->pluck('id'));

        $summary = $this->summaryCents($company, $filters);

        return Inertia::render('journal-entries/index', [
            'entries' => $entries->through(fn(JournalEntry $entry): array => [
                'id' => $entry->id,
                'reference_no' => $entry->reference_no,
                'transaction_date' => $entry->transaction_date->toDateString(),
                'description' => $entry->description,
                'status' => $entry->status,
                'display_status' => ($sources[$entry->id]['released'] ?? false) && $entry->status === 'posted'
                    ? 'released'
                    : $entry->status,
                'vendor' => $sources[$entry->id]['vendor'] ?? null,
                'cheque_number' => $sources[$entry->id]['cheque_number'] ?? null,
                'source_type' => $entry->source_type,
                'line_count' => (int) $entry->lines_count,
                'debit_cents' => Money::cents($entry->lines_sum_debit),
                'credit_cents' => Money::cents($entry->lines_sum_credit),
                'is_balanced' => Money::cents($entry->lines_sum_debit) === Money::cents($entry->lines_sum_credit),
                'branch' => $entry->branch?->name,
                'period' => $entry->accountingPeriod ? [
                    'id' => $entry->accountingPeriod->id,
                    'name' => $entry->accountingPeriod->name,
                    'status' => $entry->accountingPeriod->status,
                ] : null,
                'pos_reference' => $entry->posEntry?->pos_reference,
            ])->all(),
            'pagination' => InertiaLinks::from($entries),
            'summary' => $summary,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'source_type' => $filters['source_type'] ?? '',
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'period_id' => isset($filters['period_id']) ? (int) $filters['period_id'] : null,
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'q' => $filters['q'] ?? '',
            ],
            'branches' => $company->branches()->orderBy('name')->get(['id', 'name', 'code']),
            'periods' => $company->fiscalYears()
                ->with(['periods' => fn($query) => $query->orderBy('period_number')])
                ->orderByDesc('year')
                ->get()
                ->flatMap(fn($fiscalYear) => $fiscalYear->periods->map(fn($period) => [
                    'id' => $period->id,
                    'label' => "{$period->name} · {$fiscalYear->year}",
                    'status' => $period->status,
                ])),
            'currency' => $company->base_currency_code,
        ]);
    }


    public function create(): Response
    {
        $company = CurrentCompany::find();
        $period = $this->fiscalYears->currentPeriod($company);

        return Inertia::render('journal-entries/create', [
            'accounts' => $this->accountOptions($company),
            'branches' => $company->branches()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'contacts' => $company->contacts()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'tax_identification_number', 'address']),
            'period' => $this->periodPayload($period),
            'defaults' => [
                'transaction_date' => $period && ! $period->covers(now()->toDateString())
                    ? $period->start_date->toDateString()
                    : now()->toDateString(),
                'branch_id' => null,
                'contact_id' => null,
                'description' => '',
                'reference_no' => $this->journalEntries->nextReference($company, now()->toDateString()),
            ],
            'postingBlocked' => $period === null || ! $period->allowsPosting(),
            'currency' => $company->base_currency_code,
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();
        $postNow = $request->boolean('post_now');

        $entry = $this->journalEntries->store(
            $company,
            $request->safe()->only(['reference_no', 'transaction_date', 'branch_id', 'contact_id', 'description']),
            $request->validated('lines') ?? [],
            $request->user(),
            $postNow,
        );

        return to_route('journal-entries.show', $entry)->with(
            'success',
            $postNow
                ? "Journal entry {$entry->reference_no} posted."
                : "Journal entry {$entry->reference_no} saved as a draft.",
        );
    }

    public function show(JournalEntry $journalEntry): Response
    {
        $this->guardCompany($journalEntry);

        $journalEntry->load([
            'lines.account:id,code,name,account_type_id,is_active',
            'lines.account.accountType:id,name,category,normal_balance',
            'branch:id,name,code',

            // Manual journal entry contact
            'contact:id,name,tax_identification_number,address',

            // Purchase contact/vendor
            'purchase.contact:id,name,tax_identification_number,address',

            'accountingPeriod:id,name,status,period_number,start_date,end_date,fiscal_year_id',
            'fiscalYear:id,year,status',
            'creator:id,name',
            'poster:id,name',
            'posEntry:id,journal_entry_id,branch_id,pos_reference,status',
            'reversesEntry:id,reference_no,transaction_date',
            'reversal:id,reverses_id,reference_no,transaction_date,status',
            'purchase.lines.account:id,code,name',
        ]);

        $totals = $journalEntry->totalsCents();

        return Inertia::render('journal-entries/show', [
            'workbookPreview' => $journalEntry->workbook_snapshot,
            'entry' => [
                'id' => $journalEntry->id,
                'reference_no' => $journalEntry->reference_no,
                'transaction_date' => $journalEntry->transaction_date->toDateString(),
                'description' => $journalEntry->description,
                'status' => $journalEntry->status,
                'source_type' => $journalEntry->source_type,
                'source_reference' => $journalEntry->source_reference,
                'created_at' => $journalEntry->created_at?->toDateTimeString(),
                'posted_at' => $journalEntry->posted_at?->toDateTimeString(),
                'created_by' => $journalEntry->creator?->name,
                'posted_by' => $journalEntry->poster?->name,
                'branch' => $journalEntry->branch ? ['id' => $journalEntry->branch->id, 'name' => $journalEntry->branch->name] : null,
                'contact' => (
                    $journalEntry->source_type === 'purchase'
                    ? $journalEntry->purchase?->contact
                    : $journalEntry->contact
                ) ? [
                    'id' => (
                        $journalEntry->source_type === 'purchase'
                        ? $journalEntry->purchase->contact
                        : $journalEntry->contact
                    )->id,
                    'name' => (
                        $journalEntry->source_type === 'purchase'
                        ? $journalEntry->purchase->contact
                        : $journalEntry->contact
                    )->name,
                    'tax_identification_number' => (
                        $journalEntry->source_type === 'purchase'
                        ? $journalEntry->purchase->contact
                        : $journalEntry->contact
                    )->tax_identification_number,
                    'address' => (
                        $journalEntry->source_type === 'purchase'
                        ? $journalEntry->purchase->contact
                        : $journalEntry->contact
                    )->address,
                ] : null,
                'period' => $journalEntry->accountingPeriod ? [
                    'id' => $journalEntry->accountingPeriod->id,
                    'name' => $journalEntry->accountingPeriod->name,
                    'status' => $journalEntry->accountingPeriod->status,
                    'allows_posting' => $journalEntry->accountingPeriod->allowsPosting(),
                ] : null,
                'fiscal_year' => $journalEntry->fiscalYear ? [
                    'year' => $journalEntry->fiscalYear->year,
                    'status' => $journalEntry->fiscalYear->status,
                ] : null,
                'pos_reference' => $journalEntry->posEntry?->pos_reference,
                'pos_entry_id' => $journalEntry->posEntry?->id,
                'reverses' => $journalEntry->reversesEntry?->reference_no,
                'reversal' => $journalEntry->reversal ? [
                    'id' => $journalEntry->reversal->id,
                    'reference_no' => $journalEntry->reversal->reference_no,
                    'transaction_date' => $journalEntry->reversal->transaction_date->toDateString(),
                ] : null,
                'lines' => $journalEntry->lines->map(fn($line): array => [
                    'id' => $line->id,
                    'account_id' => $line->account_id,
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name ?? $line->description,
                    'account_category' => $line->account?->accountType?->category,
                    'account_inactive' => $line->account ? ! $line->account->is_active : true,
                    'debit_cents' => Money::cents($line->debit),
                    'credit_cents' => Money::cents($line->credit),
                    'description' => $line->description,
                ]),

                'purchase' => $journalEntry->source_type === 'purchase' && $journalEntry->purchase
                    ? [
                        'id' => $journalEntry->purchase->id,
                        'purchase_date' => $journalEntry->purchase->purchase_date->toDateString(),
                        'reference_no' => $journalEntry->purchase->reference_no,
                        'vendor_name' => $journalEntry->purchase->vendor_name,

                        'contact' => $journalEntry->purchase->contact
                            ? [
                                'id' => $journalEntry->purchase->contact->id,
                                'name' => $journalEntry->purchase->contact->name,
                                'tax_identification_number' => $journalEntry->purchase->contact->tax_identification_number,
                                'address' => $journalEntry->purchase->contact->address,
                            ]
                            : null,

                        'lines' => $journalEntry->purchase->lines->map(fn($line): array => [
                            'id' => $line->id,
                            'particulars' => $line->particulars,
                            'quantity' => (float) $line->quantity,
                            'uom' => $line->uom,
                            'unit_price' => (float) $line->unit_price,
                            'amount_cents' => Money::cents($line->amount),
                            'account' => $line->account ? [
                                'code' => $line->account->code,
                                'name' => $line->account->name,
                            ] : null,
                        ])->values(),
                    ]
                    : null,
            ],
            'totals' => [
                'debits' => $totals['debits'],
                'credits' => $totals['credits'],
                'difference' => $totals['debits'] - $totals['credits'],
            ],
            'actions' => [
                'can_edit' => $journalEntry->isEditable(),
                'can_post' => $journalEntry->workbook_snapshot === null && $journalEntry->isDraft() && ($journalEntry->accountingPeriod?->allowsPosting() ?? false),
                'can_unpost' => $journalEntry->isPosted() && $journalEntry->isManual(),
                'can_reverse' => $journalEntry->isReversible(),
                'can_delete' => $journalEntry->isEditable(),
            ],
            'currency' => $journalEntry->company->base_currency_code,
        ]);
    }

    public function edit(JournalEntry $journalEntry): Response|RedirectResponse
    {
        $this->guardCompany($journalEntry);

        if (! $journalEntry->isEditable()) {
            return to_route('journal-entries.show', $journalEntry)->with(
                'error',
                'Only draft manual entries can be edited.',
            );
        }

        $journalEntry->load('lines.account:id,code,name');

        $company = $journalEntry->company;

        $accounts = $this->accountOptions($company, $journalEntry->lines->pluck('account')->filter()->all());
        return Inertia::render('journal-entries/edit', [
            'entry' => [
                'id' => $journalEntry->id,
                'reference_no' => $journalEntry->reference_no,
                'transaction_date' => $journalEntry->transaction_date->toDateString(),
                'description' => $journalEntry->description,
                'branch_id' => $journalEntry->branch_id,
                'contact_id' => $journalEntry->contact_id,
                'lines' => $journalEntry->lines->map(fn($line): array => [
                    'account_id' => $line->account_id,
                    'debit' => Money::cents($line->debit) / 100,
                    'credit' => Money::cents($line->credit) / 100,
                    'description' => $line->description ?? '',
                ]),
            ],
            'accounts' => $accounts,
            'branches' => $company->branches()->orderBy('name')->get(['id', 'name', 'code']),
            'contacts' => $company->contacts()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'tax_identification_number', 'address']),
            'period' => $this->periodPayload($journalEntry->accountingPeriod ?? $this->fiscalYears->periodFor($company, $journalEntry->transaction_date)),
            'postingBlocked' => ! ($journalEntry->accountingPeriod?->allowsPosting() ?? false),
            'currency' => $company->base_currency_code,
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $postNow = $request->boolean('post_now');

        $entry = $this->journalEntries->update(
            $journalEntry,
            $request->safe()->only(['reference_no', 'transaction_date', 'branch_id', 'contact_id', 'description']),
            $request->validated('lines') ?? [],
            $request->user(),
            $postNow,
        );

        return to_route('journal-entries.show', $entry)->with(
            'success',
            $postNow
                ? "Journal entry {$entry->reference_no} posted."
                : "Journal entry {$entry->reference_no} updated.",
        );
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        $this->guardCompany($journalEntry);

        $this->journalEntries->delete($journalEntry);

        return to_route('journal-entries.index')->with('success', 'Draft entry removed.');
    }

    public function post(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->guardCompany($journalEntry);

        $entry = $this->journalEntries->post($journalEntry, $request->user());

        return to_route('journal-entries.show', $entry)->with('success', "Journal entry {$entry->reference_no} posted.");
    }

    public function unpost(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->guardCompany($journalEntry);

        $entry = $this->journalEntries->unpost($journalEntry);

        return to_route('journal-entries.show', $entry)->with('success', 'Entry returned to draft.');
    }

    /**
     * Reversals are dated: the correcting entry belongs to the period that is
     * open now, never to the closed period it is correcting.
     */
    public function reverse(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->guardCompany($journalEntry);

        $validated = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        $reversal = $this->journalEntries->reverse(
            $journalEntry,
            $validated['transaction_date'] ?? now(),
            $request->user(),
        );

        return to_route('journal-entries.show', $reversal)->with(
            'success',
            "Reversing entry {$reversal->reference_no} posted.",
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{entries: int, drafts: int, debits: int, credits: int, difference: int}
     */
    protected function summaryCents(Company $company, array $filters): array
    {
        $base = fn() => $company->journalEntries()
            ->when($filters['status'] ?? null, fn($query, $value) => $query->where('status', $value))
            ->when($filters['source_type'] ?? null, fn($query, $value) => $query->where('source_type', $value))
            ->when($filters['branch_id'] ?? null, fn($query, $value) => $query->where('branch_id', $value))
            ->when($filters['period_id'] ?? null, fn($query, $value) => $query->where('accounting_period_id', $value))
            ->when($filters['from'] ?? null, fn($query, $value) => $query->whereDate('transaction_date', '>=', $value))
            ->when($filters['to'] ?? null, fn($query, $value) => $query->whereDate('transaction_date', '<=', $value));

        $counts = $base()
            ->selectRaw("count(*) as entry_count, sum(case when status = 'draft' then 1 else 0 end) as draft_count")
            ->first();

        $totals = DB::table('journal_entry_lines')
            ->selectRaw('coalesce(sum(journal_entry_lines.debit), 0) as debits, coalesce(sum(journal_entry_lines.credit), 0) as credits')
            ->whereIn('journal_entry_id', $base()->select('journal_entries.id'))
            ->first();

        $debits = Money::cents($totals->debits ?? 0);
        $credits = Money::cents($totals->credits ?? 0);

        return [
            'entries' => (int) ($counts->entry_count ?? 0),
            'drafts' => (int) ($counts->draft_count ?? 0),
            'debits' => $debits,
            'credits' => $credits,
            'difference' => $debits - $credits,
        ];
    }

    /**
     * Only leaf, active accounts can take a posting, so those are the options
     * offered. `$mustInclude` keeps accounts that are already on the entry.
     *
     * @param  list<Account>  $mustInclude
     * @return list<array<string, mixed>>
     */
    protected function accountOptions(Company $company, array $mustInclude = []): array
    {
        $accounts = $company->accounts()
            ->with(['accountType:id,name,category,normal_balance', 'parent:id,code,name'])
            ->withCount('children')
            ->orderBy('code')
            ->get();

        $extra = array_fill_keys(array_map(fn(Account $account): int => $account->id, $mustInclude), true);

        return $accounts
            ->filter(function (Account $account) use ($extra): bool {
                $isLeaf = (int) $account->children_count === 0;

                return ($account->is_active && $isLeaf) || isset($extra[$account->id]);
            })
            ->map(fn(Account $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => "{$account->code} · {$account->name}",
                'category' => $account->accountType?->category,
                'normal_balance' => $account->accountType?->normal_balance,
                'group' => $account->parent?->name,
                'is_active' => $account->is_active,
                'is_summary' => (int) $account->children_count > 0,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function periodPayload(mixed $period): ?array
    {
        if (! $period) {
            return null;
        }

        $period->loadMissing('fiscalYear');

        return [
            'id' => $period->id,
            'name' => $period->name,
            'number' => $period->period_number,
            'status' => $period->status,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'allows_posting' => $period->allowsPosting(),
            'fiscal_year' => $period->fiscalYear ? [
                'year' => $period->fiscalYear->year,
                'status' => $period->fiscalYear->status,
            ] : null,
        ];
    }

    protected function guardCompany(JournalEntry $journalEntry): void
    {
        $journalEntry->loadMissing('company');

        abort_unless($journalEntry->company_id === CurrentCompany::find()->id, 404);
    }
}
