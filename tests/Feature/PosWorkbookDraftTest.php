<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Imports\PosEntryImport;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PosEntry;
use App\Services\FiscalYearService;
use App\Services\JournalEntryService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->company = Company::create(['code' => 'TEST', 'name' => 'Test', 'base_currency_code' => 'PHP', 'is_active' => true]);
    $this->branch = $this->company->branches()->create(['code' => 'MAXI', 'name' => 'Maxi', 'is_active' => true]);
    app(FiscalYearService::class)->create($this->company, ['year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class])->withoutVite();
});

function accountantRows(): array
{
    return [
        ['***DATA TO BE UPLOADED'],
        ['Date', 'Reference No.', 'Guest Name', 'Address', 'TIN', 'Business Source', 'Arrival', 'Departure', 'Basic Room Rates', 'Extra Bed', 'Room Upgrade', 'No Show', 'Early Check-in/Check-out', 'Complementary Room', 'Other Income', '', '', '', '', '', '', '', '', '', '', '', '', 'Mode of Payment', '', '', '', '', 'VATable', 'WITTHOLDING TAX', 'Discounts'],
        ['', '', '', '', '', '', '', '', '', '', '', '', '', '', 'SPA (Actual Cost)', 'SPA (Increment)', 'Laundry Bag & Laundy Services', 'Shuttle Services', 'Smoking Fee', 'Key Card (Actua Cost)', 'Key Card (Increment)', 'Amenities (Pillows, Robes, etc) Actual Cost', 'Amenities (Pillows, Robes, etc) Increment', 'AVL (Audio, Visual, and Lighting) Actual Cost', 'AVL (Audio, Visual, and Lighting) Increment', 'Others (Flowers, slippers, cake, chocolates, balloons, etc) Actual Cost', 'Others (Flowers, slippers, cake, chocolates, balloons, etc) Increment', 'Cash', 'Card Payment', 'Bank Transfer, QR Ph, Online Booking', 'Send Bill', 'Gift Voucher'],
        [46303, '001236', 'Sample Guest', '', '', 'Corporate', '', '', 2588, 0, 0, 0, 1200, 0, 900, 300, 50, 300, 10000, 800, 200, 2000, 500, 25000, 5000, 5000, 2000, 3250, 40000, 6002.89, 2588, 3000, 'VATable Sales', 0.02, 'None'],
        ['***GENERATED SALES REPORT'],
    ];
}

it('saves the accountant journal as an unbalanced linked draft with a precise snapshot', function () {
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    $import->collection(collect(accountantRows())->map(fn ($row) => collect($row)));
    $pos = PosEntry::firstOrFail();
    $journal = $pos->journalEntry;
    $preview = $journal->workbook_snapshot;

    expect($pos->calculation_version)->toBe('sales_format_3')
        ->and($pos->gross_sales)->toBe('55838.00')
        ->and($pos->amount_due)->toBe('54840.89')
        ->and($pos->key_card_increment_amount)->toBe('200.00')
        ->and($journal->status)->toBe('draft')
        ->and($journal->source_type)->toBe('pos')
        ->and($journal->lines)->toHaveCount(23)
        ->and(round($preview['debits'] / 100, 2))->toBe(58638.0)
        ->and(round($preview['credits'] / 100, 2))->toBe(58499.21)
        ->and(round($preview['difference'] / 100, 2))->toBe(138.78)
        ->and($journal->lines->firstWhere('description', 'Unearned Revenue - Gift Voucher')->debit)->toBe('3000.00')
        ->and($journal->lines->firstWhere('description', 'Room Revenue')->credit)->toBe('788.00');
    expect(fn () => app(JournalEntryService::class)->post($journal))->toThrow(ValidationException::class);
    $this->get('/pos-entries/imports/'.$import->batchId)->assertOk()->assertInertia(fn ($page) => $page
        ->component('pos-entries/import-report')->has('entries', 1)
        ->where('branchId', $this->branch->id)
        ->where('entries.0.pos_reference', '001236')->where('entries.0.journal_entry_id', $journal->id));
    $this->get('/pos-entries/'.$pos->id)->assertOk();
    $this->get('/journal-entries/'.$journal->id)->assertOk()->assertInertia(fn ($page) => $page
        ->where('entry.pos_entry_id', $pos->id)->where('actions.can_post', false)
        ->has('workbookPreview.rows', 29));
});

it('creates a linked draft from the manual entry form', function () {
    $this->post('/pos-entries', [
        'branch_id' => $this->branch->id, 'business_date' => '2026-10-08',
        'pos_reference' => 'MANUAL', 'calculation_version' => 'sales_format_3',
        'basic_room_rate_amount' => 1120, 'cash_amount' => 1120,
        'vat_classification' => 'vatable', 'discount_type' => 'none',
        'key_card_amount' => 80, 'key_card_increment_amount' => 20,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $pos = PosEntry::firstOrFail();
    expect($pos->gross_sales)->toBe('1220.00')->and($pos->journalEntry->status)->toBe('draft');
});

it('sorts the report and keeps amenities increments specific to each invoice', function () {
    $rows = accountantRows();
    $second = $rows[3];
    $second[1] = '001235';
    $second[22] = 100;
    array_splice($rows, 4, 0, [$second]);
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    $import->collection(collect($rows)->map(fn ($row) => collect($row)));
    $pos = PosEntry::where('pos_reference', '001235')->firstOrFail();
    expect($pos->journalEntry->lines->firstWhere('description', 'Other Income - Amenities')->credit)->toBe('100.00');
    $this->get('/pos-entries/imports/'.$import->batchId)->assertOk()->assertInertia(fn ($page) => $page
        ->has('entries', 2)->where('entries.0.pos_reference', '001235')->where('entries.1.pos_reference', '001236'));
    $otherCompany = Company::create(['code' => 'OTHER', 'name' => 'Other', 'base_currency_code' => 'PHP', 'is_active' => true]);
    $foreignBatch = (string) Str::uuid();
    $foreignBranch = $otherCompany->branches()->create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
    $otherCompany->posEntries()->create(['branch_id' => $foreignBranch->id, 'business_date' => '2026-10-08', 'pos_reference' => 'FOREIGN', 'import_batch_id' => $foreignBatch]);
    $this->get('/pos-entries/imports/'.$foreignBatch)->assertNotFound();
});

it('rolls back an import when the invoice has no accounting period', function () {
    $rows = accountantRows();
    $rows[3][0] = '2030-10-08';
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    expect(fn () => $import->collection(collect($rows)->map(fn ($row) => collect($row))))
        ->toThrow(RuntimeException::class, 'accounting period');
    expect(PosEntry::count())->toBe(0)->and(JournalEntry::count())->toBe(0);
});

it('follows the Maxi Final layout for service income and returns the small workbook variance', function () {
    $headers = [
        'Date', 'Reference No.', 'Guest Name', 'Address', 'TIN', 'Business Source',
        'Basic Room Rates', 'Extra Bed', 'Room Upgrade', 'No Show', 'Early Check-in/Check-out', 'Complementary Room',
        'SPA (Actual Cost)', 'SPA (Increment)', 'Laundry Bag & Laundry Services (Actual Cost)', 'Laundry Bag & Laundry Services (Increment)',
        'Shuttle Services', 'Smoking Fee', 'Key Card (Actual Cost)', 'Key Card (Increment)',
        'Amenities (Pillows, Robes, etc) Actual Cost', 'Amenities (Pillows, Robes, etc) Increment',
        'AVL (Audio, Visual, and Lighting) Actual Cost', 'AVL (Audio, Visual, and Lighting) Increment',
        'Others (Flowers, slippers, cake, chocolates, balloons, etc) Actual Cost', 'Others (Flowers, slippers, cake, chocolates, balloons, etc) Increment',
        'Cash', 'Card Payment', 'Bank Transfer, QR Ph, Online Booking', 'Send Bill', 'Gift Voucher',
        'VATable', 'Withholding Tax', 'Discounts',
    ];
    $values = [
        46303, '001236', 'Eduard Navarro (Gsmi)', '', '', 'Corporate',
        2588, 0, 0, 0, 1200, 0,
        900, 300, 25, 25, 300, 10000, 800, 200, 2000, 500, 25000, 5000, 5000, 2000,
        3250, 40000, 6002.892857142857, 2588, 3000, 'VATable Sales', 0.02, 'None',
    ];
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    $import->collection(collect([$headers, $values])->map(fn ($row) => collect($row)));
    $pos = PosEntry::firstOrFail();
    $journal = $pos->journalEntry;
    $preview = $journal->workbook_snapshot;

    expect($pos->calculation_version)->toBe('sales_format_4')
        ->and($pos->gross_sales)->toBe('55838.00')
        ->and($pos->amount_due)->toBe('54840.89')
        ->and($pos->laundry_amount)->toBe('25.00')
        ->and($pos->laundry_increment_amount)->toBe('25.00')
        ->and(round($preview['debits'] / 100, 2))->toBe(55516.57)
        ->and(round($preview['credits'] / 100, 2))->toBe(55516.57)
        ->and(round($preview['difference'] / 100, 2))->toBe(0.0)
        ->and(round($preview['rows'][6]['amount'] / 100, 2))->toBe(5661.21)
        ->and(round($journal->lines->firstWhere('description', 'Other Income - SPA')->credit, 2))->toBe(171.43)
        ->and($journal->lines->firstWhere('description', 'Inventory - Laundry Bag')->credit)->toBe('25.00');
});

it('uses the accountant final VAT-exempt report formula', function () {
    $headers = ['Date', 'Reference No.', 'Basic Room Rates', 'Cash', 'VATable', 'Laundry Bag & Laundry Services (Increment)'];
    $rows = [$headers, [46303, '001245', 3288, 3288, 'VAT Exempt Sales', 0]];
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    $import->collection(collect($rows)->map(fn ($row) => collect($row)));
    $pos = PosEntry::firstOrFail();
    $sales = $pos->journalEntry->workbook_snapshot['sales'];

    expect($pos->gross_sales)->toBe('3288.00')
        ->and(round($sales['taxable_amount'] / 100, 2))->toBe(0.0)
        ->and(round($sales['exempted_amount'] / 100, 2))->toBe(2935.71)
        ->and($pos->amount_due)->toBe('2935.71');
});

it('redirects an uploaded workbook to its sales report', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray(accountantRows(), null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'pos-workbook-');
    try {
        (new Xlsx($book))->save($path);
        $response = $this->post('/pos-entries/import', [
            'branch_id' => $this->branch->id,
            'file' => new UploadedFile($path, 'sales.xlsx', null, null, true),
        ]);
        $pos = PosEntry::firstOrFail();
        $response->assertSessionHasNoErrors()->assertRedirect(route('pos-entries.import-report', $pos->import_batch_id));
        expect($pos->journal_entry_id)->not->toBeNull();
    } finally {
        @unlink($path);
    }
});

it('rolls back both invoice and draft journals when a later row is invalid', function () {
    $rows = accountantRows();
    array_splice($rows, 4, 0, [$rows[3]]);
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    expect(fn () => $import->collection(collect($rows)->map(fn ($row) => collect($row))))->toThrow(RuntimeException::class);
    expect(PosEntry::count())->toBe(0)->and(JournalEntry::count())->toBe(0)->and($import->imported())->toBe(0);
});

it('updates the same draft and removes it when its POS invoice is deleted', function () {
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    $import->collection(collect(accountantRows())->map(fn ($row) => collect($row)));
    $pos = PosEntry::firstOrFail();
    $journalId = $pos->journal_entry_id;
    $data = $pos->getAttributes();
    $data['business_date'] = '2026-10-08';
    $data['cash_amount'] = 4000;
    $this->put('/pos-entries/'.$pos->id, $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($pos->fresh()->journal_entry_id)->toBe($journalId)
        ->and(JournalEntry::count())->toBe(1)
        ->and($pos->fresh()->journalEntry->lines->firstWhere('description', 'Cash on Hand')->debit)->toBe('4000.00');
    $this->delete('/pos-entries/'.$pos->id)->assertRedirect();
    expect(JournalEntry::count())->toBe(0);
});
