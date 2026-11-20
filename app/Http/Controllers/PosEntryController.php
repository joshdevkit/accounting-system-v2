<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePosEntryRequest;
use App\Http\Requests\UpdatePosEntryRequest;
use App\Imports\PosEntryImport;
use App\Imports\PosEntryWorkbookImport;
use App\Models\Branch;
use App\Models\PosEntry;
use App\Services\JournalEntryService;
use App\Services\PosPostingService;
use App\Services\PosSalesCalculationService;
use App\Support\CurrentCompany;
use App\Support\InertiaLinks;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PosEntryController extends Controller
{
    public function __construct(
        protected PosPostingService $posting,
    ) {}

    public function index(Request $request): Response
    {
        $company = CurrentCompany::find();

        $filters = $request->only(['status', 'branch_id', 'from', 'to', 'q']);
        if (! isset($filters['branch_id']) || $filters['branch_id'] === '') {
            $filters['branch_id'] = $company->branches()->active()->orderBy('name')->orderBy('id')->value('id');
        }

        $base = fn () => $company->posEntries()
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['branch_id'] ?? null, fn ($query, $value) => $query->where('branch_id', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('business_date', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('business_date', '<=', $value))
            ->when($filters['q'] ?? null, function ($query) use ($filters): void {
                $term = (string) $filters['q'];

                $query->where('pos_reference', 'like', "%{$term}%");
            });

        $amountFields = array_merge(
            (array) config('accounting.pos.amount_fields'),
            (array) config('accounting.pos.payment_fields'),
        );

        $entries = $base()
            ->with(['branch:id,name,code', 'journalEntry:id,reference_no,status', 'creator:id,name'])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $totals = DB::table('pos_entries')
            ->selectRaw(implode(', ', array_map(
                fn (string $field): string => "coalesce(sum({$field}), 0) as {$field}",
                $amountFields,
            )))
            ->whereIn('id', $base()->select('pos_entries.id'))
            ->first();
        $vatableBase = DB::table('pos_entries')
            ->whereIn('id', $base()->select('pos_entries.id'))
            ->whereNotNull('calculation_version')
            ->where('vat_classification', 'vatable')
            ->sum('taxable_amount');
        $manualVat = DB::table('pos_entries')
            ->whereIn('id', $base()->select('pos_entries.id'))
            ->whereNull('calculation_version')
            ->sum('vat_amount');
        $vatTotalCents = (int) round(Money::cents($vatableBase) * 0.12)
            + Money::cents($manualVat);

        return Inertia::render('pos-entries/index', [
            'entries' => $entries->through(fn (PosEntry $pos): array => [
                'id' => $pos->id,
                'business_date' => $pos->business_date->toDateString(),
                'pos_reference' => $pos->pos_reference,
                'status' => $pos->status,
                'branch' => $pos->branch ? ['id' => $pos->branch->id, 'name' => $pos->branch->name, 'code' => $pos->branch->code] : null,
                'journal_entry' => $pos->journalEntry ? [
                    'id' => $pos->journalEntry->id,
                    'reference_no' => $pos->journalEntry->reference_no,
                    'status' => $pos->journalEntry->status,
                ] : null,
                'created_by' => $pos->creator?->name,
                'amounts' => [
                    'gross_sales' => Money::cents($pos->gross_sales),
                    'discount_amount' => Money::cents($pos->discount_amount),
                    'taxable_amount' => Money::cents($pos->taxable_amount),
                    'exempted_amount' => Money::cents($pos->exempted_amount),
                    'vat_amount' => Money::cents($pos->vat_amount),
                    'local_tax_amount' => Money::cents($pos->local_tax_amount),
                    'service_charge_amount' => Money::cents($pos->service_charge_amount),
                    'net_sales' => Money::cents($pos->net_sales),
                ],
                'tendered_cents' => $pos->tenderedCents(),
                'variance_cents' => $pos->varianceCents(),
            ])->all(),
            'pagination' => InertiaLinks::from($entries),
            'totals' => [
                'gross_sales' => Money::cents($totals->gross_sales ?? 0),
                'discount_amount' => Money::cents($totals->discount_amount ?? 0),
                'taxable_amount' => Money::cents($totals->taxable_amount ?? 0),
                'exempted_amount' => Money::cents($totals->exempted_amount ?? 0),
                'vat_amount' => $vatTotalCents,
                'local_tax_amount' => Money::cents($totals->local_tax_amount ?? 0),
                'service_charge_amount' => Money::cents($totals->service_charge_amount ?? 0),
                'net_sales' => Money::cents($totals->net_sales ?? 0),
            ],

            'filters' => [
                'status' => $filters['status'] ?? '',
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'q' => $filters['q'] ?? '',
            ],
            'branches' => $company->branches()->orderBy('name')->get(['id', 'name', 'code', 'is_active']),
            'paymentLabels' => $this->paymentLabels(),
            'currency' => $company->base_currency_code,
        ]);
    }

    public function create(): Response
    {
        $company = CurrentCompany::find();

        return Inertia::render('pos-entries/create', [
            'branches' => $company->branches()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'mapping' => $this->posting->mapping($company, $company->branches()->active()->orderBy('id')->first()),
            'mappingByBranch' => $company->branches()->get()->mapWithKeys(fn (Branch $branch): array => [$branch->id => $this->posting->mapping($company, $branch)]),
            'salesOptions' => $this->salesOptions(),
            'paymentLabels' => $this->paymentLabels(),
            'defaults' => [
                'branch_id' => $company->branches()->active()->orderBy('id')->value('id'),
                'business_date' => now()->toDateString(),
                'pos_reference' => '',
            ],
            'currency' => $company->base_currency_code,
        ]);
    }

    public function store(StorePosEntryRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $pos = DB::transaction(function () use ($company, $request) {
            $pos = $company->posEntries()->create([
                ...collect($request->validated())->except('post_now')->all(),
                'status' => 'draft',
                'created_by' => $request->user()?->id,
            ]);
            if (in_array($pos->calculation_version, ['sales_format_3', 'sales_format_4'], true)) {
                app(JournalEntryService::class)->savePosDraft($pos, $request->user());
            }

            return $pos;
        });

        if ($request->boolean('post_now')) {
            $entry = $this->posting->post($pos, $request->user());

            return to_route('pos-entries.show', $pos)->with(
                'success',
                "POS summary {$pos->pos_reference} posted as {$entry->reference_no}.",
            );
        }

        return to_route('pos-entries.show', $pos)->with('success', "POS summary {$pos->pos_reference} saved as a draft.");
    }

    public function show(PosEntry $posEntry): Response
    {
        $this->guardCompany($posEntry);

        if ($posEntry->isDraft() && $posEntry->hasIncomeDetails()) {
            $posEntry->fill(app(PosSalesCalculationService::class)->attributes($posEntry->getAttributes()));
        }

        $posEntry->load([
            'branch:id,name,code,address,phone',
            'journalEntry.lines.account:id,code,name',
            'journalEntry.accountingPeriod:id,name,status',
            'journalEntry.branch:id,name',
            'creator:id,name',
            'poster:id,name',
            'voider:id,name',
        ]);

        $lines = [];
        $totals = ['debits' => 0, 'credits' => 0];

        foreach ($posEntry->journalEntry?->lines ?? [] as $line) {
            $debit = Money::cents($line->debit);
            $credit = Money::cents($line->credit);

            $totals['debits'] += $debit;
            $totals['credits'] += $credit;

            $lines[] = [
                'id' => $line->id,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'debit_cents' => $debit,
                'credit_cents' => $credit,
                'description' => $line->description,
            ];
        }

        return Inertia::render('pos-entries/show', [
            'importReportUrl' => $posEntry->import_batch_id ? route('pos-entries.import-report', $posEntry->import_batch_id) : null,
            'workbookPreview' => $posEntry->hasIncomeDetails() ? $this->posting->workbookPreview($posEntry) : null,
            'pos' => [
                'id' => $posEntry->id,
                'business_date' => $posEntry->business_date->toDateString(),
                'pos_reference' => $posEntry->pos_reference,
                'status' => $posEntry->status,
                'notes' => $posEntry->notes,
                'sales_details' => [
                    'guest_name' => $posEntry->guest_name,
                    'address' => $posEntry->address,
                    'tin' => $posEntry->tin,
                    'business_source' => $posEntry->business_source,
                    'vat_classification' => $posEntry->vat_classification,
                    'discount_type' => $posEntry->discount_type,
                    'withholding_tax_rate' => $posEntry->withholding_tax_rate,
                    'discount_rate' => $posEntry->discount_rate,
                    'avl_increment_amount' => $posEntry->avl_increment_amount,
                    'other_suppliers_increment_amount' => $posEntry->other_suppliers_increment_amount,
                    'massage_increment_amount' => $posEntry->massage_increment_amount,
                    'laundry_increment_amount' => $posEntry->laundry_increment_amount,
                    'arrival' => $posEntry->arrival?->format('Y-m-d\\TH:i'),
                    'departure' => $posEntry->departure?->format('Y-m-d\\TH:i'),
                ],
                'created_at' => $posEntry->created_at?->toDateTimeString(),
                'posted_at' => $posEntry->posted_at?->toDateTimeString(),
                'voided_at' => $posEntry->voided_at?->toDateTimeString(),
                'created_by' => $posEntry->creator?->name,
                'posted_by' => $posEntry->poster?->name,
                'voided_by' => $posEntry->voider?->name,
                'branch' => $posEntry->branch ? [
                    'id' => $posEntry->branch->id,
                    'name' => $posEntry->branch->name,
                    'code' => $posEntry->branch->code,
                ] : null,
                'amounts' => collect($this->amountFields())
                    ->mapWithKeys(fn (string $field): array => [$field => Money::cents($posEntry->{$field})])
                    ->all(),
                'tenders' => collect($this->paymentLabels())
                    ->map(fn (string $label, string $field): array => [
                        'field' => $field,
                        'label' => $label,
                        'cents' => Money::cents($posEntry->{$field}),
                    ])
                    ->values()
                    ->all(),
                'tendered_cents' => $posEntry->tenderedCents(),
                'expected_cents' => $posEntry->expectedTenderCents(),
                'variance_cents' => $posEntry->varianceCents(),
            ],
            'journalEntry' => $posEntry->journalEntry ? [
                'id' => $posEntry->journalEntry->id,
                'reference_no' => $posEntry->journalEntry->reference_no,
                'status' => $posEntry->journalEntry->status,
                'period' => $posEntry->journalEntry->accountingPeriod?->name,
                'branch' => $posEntry->journalEntry->branch?->name,
                'lines' => $lines,
                'totals' => $totals,
            ] : null,
            'actions' => [
                'can_edit' => $posEntry->isEditable(),
                'can_post' => $posEntry->isDraft() && ! $posEntry->hasIncomeDetails(),
                'can_void' => ! $posEntry->isVoided(),
                'can_delete' => $posEntry->isDraft(),
            ],
            'mapping' => $this->posting->mapping($posEntry->company, $posEntry->branch),
            'summary' => $this->posting->summary($posEntry),
            'currency' => $posEntry->company->base_currency_code,
        ]);
    }

    public function edit(PosEntry $posEntry): Response|RedirectResponse
    {
        $this->guardCompany($posEntry);

        if (! $posEntry->isEditable()) {
            return to_route('pos-entries.show', $posEntry)->with(
                'error',
                'Posted summaries can only be voided, so the ledger stays auditable.',
            );
        }

        $company = $posEntry->company;

        return Inertia::render('pos-entries/edit', [
            'pos' => [
                'id' => $posEntry->id,
                'branch_id' => $posEntry->branch_id,
                'business_date' => $posEntry->business_date->toDateString(),
                'pos_reference' => $posEntry->pos_reference,
                'notes' => $posEntry->notes,
                'calculation_version' => $posEntry->calculation_version,
                'sales_details' => [
                    'guest_name' => $posEntry->guest_name,
                    'address' => $posEntry->address,
                    'tin' => $posEntry->tin,
                    'business_source' => $posEntry->business_source,
                    'vat_classification' => $posEntry->vat_classification,
                    'discount_type' => $posEntry->discount_type,
                    'withholding_tax_rate' => $posEntry->withholding_tax_rate,
                    'discount_rate' => $posEntry->discount_rate,
                    'avl_increment_amount' => $posEntry->avl_increment_amount,
                    'other_suppliers_increment_amount' => $posEntry->other_suppliers_increment_amount,
                    'massage_increment_amount' => $posEntry->massage_increment_amount,
                    'laundry_increment_amount' => $posEntry->laundry_increment_amount,
                    'key_card_increment_amount' => $posEntry->key_card_increment_amount,
                    'amenities_increment_amount' => $posEntry->amenities_increment_amount,
                    'arrival' => $posEntry->arrival?->format('Y-m-d\\TH:i'),
                    'departure' => $posEntry->departure?->format('Y-m-d\\TH:i'),
                ],
                'amounts' => collect($this->amountFields())
                    ->mapWithKeys(fn (string $field): array => [$field => Money::cents($posEntry->{$field}) / 100])
                    ->all(),
            ],
            'branches' => $company->branches()->orderBy('name')->get(['id', 'name', 'code']),
            'mapping' => $this->posting->mapping($company, $company->branches()->active()->orderBy('id')->first()),
            'mappingByBranch' => $company->branches()->get()->mapWithKeys(fn (Branch $branch): array => [$branch->id => $this->posting->mapping($company, $branch)]),
            'salesOptions' => $this->salesOptions(),
            'paymentLabels' => $this->paymentLabels(),
            'currency' => $company->base_currency_code,
        ]);
    }

    public function update(UpdatePosEntryRequest $request, PosEntry $posEntry): RedirectResponse
    {
        $this->guardCompany($posEntry);
        if (! $posEntry->isEditable()) {
            return to_route('pos-entries.show', $posEntry)->with('error', 'Only draft summaries can be changed.');
        }

        DB::transaction(function () use ($posEntry, $request) {
            $posEntry->fill(collect($request->validated())->except('post_now')->all())->save();
            if (in_array($posEntry->calculation_version, ['sales_format_3', 'sales_format_4'], true)) {
                app(JournalEntryService::class)->savePosDraft($posEntry, $request->user());
            }
        });

        if ($request->boolean('post_now')) {
            $entry = $this->posting->post($posEntry, $request->user());

            return to_route('pos-entries.show', $posEntry)->with(
                'success',
                "POS summary {$posEntry->pos_reference} posted as {$entry->reference_no}.",
            );
        }

        return to_route('pos-entries.show', $posEntry)->with('success', 'POS summary updated.');
    }

    public function destroy(PosEntry $posEntry): RedirectResponse
    {
        $this->guardCompany($posEntry);

        if (! $posEntry->isDraft()) {
            throw ValidationException::withMessages([
                'pos_entry' => 'Only draft summaries can be deleted. Void a posted one instead.',
            ]);
        }

        DB::transaction(function () use ($posEntry) {
            $journal = $posEntry->journalEntry;
            if ($journal && $journal->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'The linked journal is no longer a draft.']);
            }
            $posEntry->delete();
            $journal?->delete();
        });

        return to_route('pos-entries.index')->with('success', 'Draft summary removed.');
    }

    public function post(Request $request, PosEntry $posEntry): RedirectResponse
    {
        $this->guardCompany($posEntry);

        $entry = $this->posting->post($posEntry, $request->user());

        return to_route('pos-entries.show', $posEntry)->with(
            'success',
            "POS summary {$posEntry->pos_reference} posted as {$entry->reference_no}.",
        );
    }

    public function void(Request $request, PosEntry $posEntry): RedirectResponse
    {
        $this->guardCompany($posEntry);

        $validated = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        $this->posting->void($posEntry, $validated['transaction_date'] ?? now(), $request->user());

        return to_route('pos-entries.show', $posEntry)->with('success', "POS summary {$posEntry->pos_reference} voided.");
    }

    public function import(Request $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $company->id)->where('is_active', true)],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $company = CurrentCompany::find();

        $import = new PosEntryImport($company, (int) $data['branch_id'], $request->user());

        try {
            $file = $request->file('file');
            $names = IOFactory::createReaderForFile($file->getRealPath())->listWorksheetNames($file->getRealPath());
            $branch = $company->branches()->findOrFail($data['branch_id']);
            $sheet = collect($names)->first(fn (string $name): bool => strcasecmp($name, $branch->code.' (Final)') === 0)
                ?? collect($names)->first(fn (string $name): bool => strcasecmp($name, 'Maxi (Final)') === 0 && strcasecmp($branch->code, 'MAXI') === 0)
                ?? collect($names)->first(fn (string $name): bool => strcasecmp($name, $branch->code) === 0);
            if ($sheet === null && count($names) === 1) {
                $sheet = $names[0];
            }
            if ($sheet === null) {
                throw new \RuntimeException('No worksheet matches the selected branch. Use a sheet named '.$branch->code.' or a workbook containing one sales sheet.');
            }
            DB::transaction(fn () => Excel::import(new PosEntryWorkbookImport($sheet, $import), $file));
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Could not read that file: '.$e->getMessage()]);
        }

        $imported = $import->imported();

        if ($imported === 0) {
            return back()->withErrors(['file' => 'No recognizable POS rows were found in that file.']);
        }

        return to_route('pos-entries.import-report', $import->batchId)->with('success', "Imported {$imported} POS entries as drafts.");
    }

    public function importReport(string $batch): Response
    {
        $company = CurrentCompany::find();
        $entries = $company->posEntries()->with('journalEntry')->where('import_batch_id', $batch)->get()
            ->sort(fn (PosEntry $first, PosEntry $second) => strnatcmp($first->pos_reference, $second->pos_reference))->values();
        abort_if($entries->isEmpty(), 404);

        return Inertia::render('pos-entries/import-report', [
            'branchId' => $entries->first()->branch_id,
            'entries' => $entries->map(fn (PosEntry $pos) => [
                'id' => $pos->id, 'business_date' => $pos->business_date->toDateString(),
                'pos_reference' => $pos->pos_reference, 'guest_name' => $pos->guest_name,
                'address' => $pos->address, 'tin' => $pos->tin,
                'business_source' => config('accounting.pos.business_sources.'.$pos->business_source, $pos->business_source),
                'journal_entry_id' => $pos->journal_entry_id,
                'sales' => $pos->journalEntry?->workbook_snapshot['sales'] ?? app(PosSalesCalculationService::class)->calculate($pos->getAttributes()),
            ]),
            'currency' => $company->base_currency_code,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function amountFields(): array
    {
        return array_values(array_merge(
            (array) config('accounting.pos.amount_fields'),
            (array) config('accounting.pos.payment_fields'),
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function paymentLabels(): array
    {
        $labels = (array) config('accounting.pos.payment_labels');
        $fields = (array) config('accounting.pos.payment_fields');

        return array_intersect_key($labels, array_flip(array_values($fields)));
    }

    protected function salesOptions(): array
    {
        return [
            'incomeFields' => config('accounting.pos.income_fields'),
            'businessSources' => config('accounting.pos.business_sources'),
            'vatClassifications' => config('accounting.pos.vat_classifications'),
            'discountTypes' => config('accounting.pos.discount_types'),
            'discountRates' => config('accounting.pos.discount_rates'),
            'withholdingTaxRates' => config('accounting.pos.withholding_tax_rates'),
        ];
    }

    protected function guardCompany(PosEntry $posEntry): void
    {
        abort_unless($posEntry->company_id === CurrentCompany::find()->id, 404);
    }
}
