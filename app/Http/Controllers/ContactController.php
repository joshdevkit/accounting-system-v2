<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Support\CurrentCompany;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CurrentCompany::find();

        $contacts = $company->contacts()
            ->when(
                $request->filled('type') && $request->input('type') !== 'both',
                fn($query) => $query->whereIn('type', [$request->input('type'), 'both']),
            )
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = (string) $request->input('q');
                $query->where('name', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $isCustomerView = $request->input('type') === 'customer';
        
        return Inertia::render('contacts/index', [
            'contacts' => $contacts->through(function (Contact $contact) use ($isCustomerView): array {
                $debit = $isCustomerView ? $contact->totalDebitCents() : null;
                $credit = $isCustomerView ? $contact->totalCreditCents() : null;

                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'contact_person' => $contact->contact_person,
                    'type' => $contact->type,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'is_active' => $contact->is_active,
                    'total_debit_cents' => $debit,
                    'total_credit_cents' => $credit,
                    'balance_cents' => $isCustomerView ? $debit - $credit : null,
                    'outstanding_payable_cents' => $contact->isVendor() ? $contact->outstandingPayableCents() : null,
                    'outstanding_receivable_cents' => $contact->isCustomer() ? $contact->outstandingReceivableCents() : null,
                ];
            })->all(),
            'filters' => [
                'type' => $request->input('type', ''),
                'q' => $request->input('q', ''),
            ],
            'currency' => $company->base_currency_code,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('contacts/create');
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $contact = $company->contacts()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()?->id,
        ]);

        return to_route('contacts.show', $contact)->with('success', "{$contact->name} added.");
    }

    public function show(Contact $contact): Response
    {
        $this->guardCompany($contact);

        $contact->load([
            'sales' => fn($query) => $query->latest('sale_date')->limit(10),
        ]);

        $releasedPurchases = $contact->purchases()
            ->where('status', 'posted')
            ->where('payment_status', 'paid')
            ->latest('paid_at')
            ->latest('id')
            ->limit(20)
            ->get();

        return Inertia::render('contacts/show', [
            'contact' => [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'tax_identification_number' => $contact->tax_identification_number,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'address' => $contact->address,
                'default_payment_terms_days' => $contact->default_payment_terms_days,
                'notes' => $contact->notes,
                'is_active' => $contact->is_active,
                'outstanding_payable_cents' => $contact->isVendor() ? $contact->outstandingPayableCents() : null,
                'outstanding_receivable_cents' => $contact->isCustomer() ? $contact->outstandingReceivableCents() : null,
            ],
            'releasedPurchases' => $releasedPurchases->map(fn($purchase) => [
                'id' => $purchase->id,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'reference_no' => $purchase->reference_no,
                'amount_cents' => Money::cents($purchase->netPayable()),
                'check_number' => $purchase->check_number,
                'released_at' => $purchase->paid_at?->toDateString(),
            ]),
            'recentSales' => $contact->sales->map(fn($sale) => [
                'id' => $sale->id,
                'sale_date' => $sale->sale_date->toDateString(),
                'status' => $sale->status,
                'payment_status' => $sale->payment_status,
                'net_receivable_cents' => Money::cents($sale->netReceivable()),
            ]),
            'currency' => $contact->company->base_currency_code,
        ]);
    }

    public function edit(Contact $contact): Response
    {
        $this->guardCompany($contact);

        return Inertia::render('contacts/edit', [
            'contact' => [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'tax_identification_number' => $contact->tax_identification_number,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'address' => $contact->address,
                'default_payment_terms_days' => $contact->default_payment_terms_days,
                'notes' => $contact->notes,
                'is_active' => $contact->is_active,
            ],
        ]);
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $this->guardCompany($contact);

        $contact->fill([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ])->save();

        return to_route('contacts.show', $contact)->with('success', 'Contact updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->guardCompany($contact);

        if ($contact->purchases()->exists() || $contact->sales()->exists()) {
            throw ValidationException::withMessages([
                'contact' => 'This contact has purchases or sales on record and cannot be deleted. Mark it inactive instead.',
            ]);
        }

        $contact->delete();

        return to_route('contacts.index')->with('success', 'Contact removed.');
    }

    public function vendorOptions(): \Illuminate\Http\JsonResponse
    {
        $company = CurrentCompany::find();

        $contacts = $company->contacts()
            ->where('is_active', true)
            ->whereIn('type', ['vendor', 'both'])
            ->orderBy('name')
            ->get(['id', 'name', 'default_payment_terms_days']);

        return response()->json($contacts->map(fn(Contact $contact) => [
            'id' => $contact->id,
            'label' => $contact->name,
            'default_payment_terms_days' => $contact->default_payment_terms_days,
        ]));
    }

    public function customerOptions(): \Illuminate\Http\JsonResponse
    {
        $company = CurrentCompany::find();

        $contacts = $company->contacts()
            ->where('is_active', true)
            ->whereIn('type', ['customer', 'both'])
            ->orderBy('name')
            ->get(['id', 'name', 'default_payment_terms_days']);

        return response()->json($contacts->map(fn(Contact $contact) => [
            'id' => $contact->id,
            'label' => $contact->name,
            'default_payment_terms_days' => $contact->default_payment_terms_days,
        ]));
    }

    protected function guardCompany(Contact $contact): void
    {
        abort_unless($contact->company_id === CurrentCompany::find()->id, 404);
    }


    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);

        $company = CurrentCompany::find();
        $file = $request->file('file');

        $reader = strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? new XlsxReader()
            : new CsvReader();

        $reader->open($file->getRealPath());

        // Turn any cell value into a clean string (Excel gives ints/floats/dates).
        $cell = function (mixed $value): string {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if (is_float($value) && floor($value) === $value) {
                $value = (int) $value; // 9171234567.0 => 9171234567
            }

            return trim((string) $value);
        };

        $header = null;
        $columns = [];
        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 0;

        $aliases = [
            'name' => ['company', 'name'],
            'contact_person' => ['contact person'],
            'email' => ['email'],
            'phone' => ['phone no.', 'phone no', 'phone'],
            'status' => ['status'],
        ];

        DB::transaction(function () use (
            $reader,
            $cell,
            $aliases,
            $company,
            $request,
            &$header,
            &$columns,
            &$created,
            &$updated,
            &$errors,
            &$line,
        ) {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map($cell, $row->toArray());

                    // First row = header
                    if ($header === null) {
                        $header = array_map(
                            fn($h) => strtolower(preg_replace('/^\xEF\xBB\xBF/', '', $h)),
                            $cells,
                        );

                        foreach ($aliases as $field => $names) {
                            foreach ($names as $name) {
                                $index = array_search($name, $header, true);
                                if ($index !== false) {
                                    $columns[$field] = $index;
                                    break;
                                }
                            }
                        }

                        if (! isset($columns['name'])) {
                            return; // handled after the transaction
                        }

                        continue;
                    }

                    if (count(array_filter($cells, fn($c) => $c !== '')) === 0) {
                        continue; // blank row
                    }

                    $get = fn(string $field): ?string => isset($columns[$field])
                        ? (($cells[$columns[$field]] ?? '') ?: null)
                        : null;

                    $data = [
                        'name' => $get('name'),
                        'contact_person' => $get('contact_person'),
                        'email' => $get('email'),
                        'phone' => $get('phone'),
                        'is_active' => ! in_array(strtolower((string) $get('status')), ['inactive', '0', 'no'], true),
                    ];

                    $validator = Validator::make($data, [
                        'name' => ['required', 'string', 'max:255'],
                        'contact_person' => ['nullable', 'string', 'max:255'],
                        'email' => ['nullable', 'email', 'max:255'],
                        'phone' => ['nullable', 'string', 'max:50'],
                    ]);

                    if ($validator->fails()) {
                        $errors[] = "Row {$line}: " . $validator->errors()->first();
                        continue;
                    }

                    $existing = $company->contacts()
                        ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
                        ->first();

                    if ($existing) {
                        $existing->update([
                            ...$data,
                            'type' => $existing->type === 'vendor' ? 'both' : $existing->type,
                        ]);
                        $updated++;
                    } else {
                        $company->contacts()->create([
                            ...$data,
                            'type' => 'customer',
                            'created_by' => $request->user()->id,
                        ]);
                        $created++;
                    }
                }

                break; // first sheet only
            }
        });

        $reader->close();

        if (! isset($columns['name'])) {
            return back()->withErrors(['file' => 'The file needs a "Company" column in the first row.']);
        }

        $message = "Imported {$created} new and updated {$updated} existing guests.";

        if ($errors) {
            $message .= ' Skipped ' . count($errors) . ' row(s): ' . implode(' | ', array_slice($errors, 0, 5));
        }

        return back()->with('success', $message);
    }
}
