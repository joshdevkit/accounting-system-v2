<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Imports\PosEntryImport;
use App\Imports\PosEntryWorkbookImport;
use App\Models\Company;
use App\Models\PosEntry;
use App\Services\PosPostingService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->company = Company::create(['code' => 'TEST', 'name' => 'Test', 'base_currency_code' => 'PHP', 'is_active' => true]);
    $this->branch = $this->company->branches()->create(['code' => 'MAXI', 'name' => 'Maxi', 'is_active' => true]);
});

function salesFormatRows(): array
{
    return [
        ['***DATA TO BE UPLOADED'],
        ['Date', 'Reference No.', 'Guest Name', 'Address', 'TIN', 'Business Source', 'Arrival', 'Departure', 'Basic Room Rates', 'Extra Bed', 'Other Income', 'Mode of Payment', '', '', '', 'VATable', 'WITTHOLDING TAX', 'Discounts'],
        ['', '', '', '', '', '', '', '', '', '', 'SPA', 'Cash', "Card\nPayment", 'Online Booking', 'Gift Voucher', 'Non-VATable', '(1% 2% 5% 6% 7%)', 'Senior Citizen 20%, Employee 25%'],
        [46303, '001234', 'Guest One', 'Manila', '001-123', 'Corporate', 46300.5, 46301.5, 4988, 100, 200, 2500, 2500, 200, 88, 'VATable Sales', 0.02, 'None'],
        [46303, '001235', 'Guest Two', '', '', '', '', '', 1000, '', '', '', '', 1000],
        ['***GENERATED SALES REPORT'],
        [46303, 'REPORT', 'Must not import', '', '', '', '', '', 999],
    ];
}

it('preserves references, guest details, classifications, income and split payments per row', function () {
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    $import->collection(collect(salesFormatRows())->map(fn (array $row) => collect($row)));
    $entry = PosEntry::where('pos_reference', '001234')->firstOrFail();

    expect($import->imported())->toBe(2)
        ->and(PosEntry::count())->toBe(2)
        ->and($entry->guest_name)->toBe('Guest One')
        ->and($entry->business_source)->toBe('corporate')
        ->and($entry->tin)->toBe('001-123')
        ->and($entry->arrival->format('H:i'))->toBe('12:00')
        ->and($entry->withholding_tax_rate)->toBe('2.00')
        ->and($entry->discount_type)->toBe('none')
        ->and($entry->vat_classification)->toBe('vatable')
        ->and($entry->gross_sales)->toBe('10576.00')
        ->and($entry->spa_amount)->toBe('200.00')
        ->and($entry->card_amount)->toBe('2500.00')
        ->and($entry->gift_voucher_amount)->toBe('88.00')
        ->and($entry->online_booking_amount)->toBe('200.00')
        ->and($entry->status)->toBe('draft')
        ->and($entry->journal_entry_id)->toBeNull()
        ->and(PosEntry::where('pos_reference', '001235')->first()->vat_classification)->toBeNull();
});

it('imports the revised headers and shows calculated sales and journal amounts on the entry', function () {
    $rows = collect([
        collect(['Date', 'Reference No.', "Basic Room Rates\n(Exclusive of Discounts - Corporate/Gov't Dsicount)", 'Extra Bed', "Complementary Room\n(No Charge - visitors)", 'Mode of Payment', '', 'VATable', 'WITTHOLDING TAX', 'Discounts']),
        collect(['', '', '', '', '', 'Cash', 'Bank Transfer, QR Ph, Online Booking']),
        collect([46303, '001234', 4988, 1200, 0, 1200, 4988, 'VATable Sales', 'None', '20% - Senior Citizen']),
    ]);
    (new PosEntryImport($this->company, $this->branch->id, null))->collection($rows);
    $pos = PosEntry::firstOrFail();
    expect($pos->basic_room_rate_amount)->toBe('4988.00')
        ->and($pos->bank_transfer_amount)->toBe('4988.00')
        ->and($pos->gross_sales)->toBe('12376.00')
        ->and($pos->amount_due)->toBe('16796.00')
        ->and($pos->expectedTenderCents())->toBe(1679600);
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class])->withoutVite();
    $this->get('/pos-entries/'.$pos->id)->assertOk()->assertInertia(fn ($page) => $page
        ->where('workbookPreview.sales.amount_due', 1679600)
        ->where('workbookPreview.rows.0.label', 'Cash on Hand')
        ->where('workbookPreview.rows.0.amount', 120000)
        ->where('actions.can_post', false)
    );
});

it('rolls back the complete batch for invalid amounts or duplicate references', function (string $badValue) {
    $rows = salesFormatRows();
    if ($badValue === 'duplicate') {
        $rows[4][1] = '001234';
    } else {
        $rows[4][8] = $badValue;
    }
    $import = new PosEntryImport($this->company, $this->branch->id, null);
    expect(fn () => $import->collection(collect($rows)->map(fn (array $row) => collect($row))))
        ->toThrow(RuntimeException::class, 'Row 5:');
    expect(PosEntry::count())->toBe(0);
})->with(['duplicate', 'invalid', '-12']);

it('imports only the selected sales worksheet from a workbook with lists and reports', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Lists')->setCellValue('A1', 'Business Source');
    $book->createSheet()->setTitle('Maxi')->fromArray(salesFormatRows(), null, 'A1', true);
    $book->createSheet()->setTitle('Gusto');
    $path = tempnam(sys_get_temp_dir(), 'pos-sales-');
    try {
        (new Xlsx($book))->save($path);
        $import = new PosEntryImport($this->company, $this->branch->id, null);
        Excel::import(new PosEntryWorkbookImport('Maxi', $import), $path, null, Maatwebsite\Excel\Excel::XLSX);
        expect($import->imported())->toBe(2)->and(PosEntry::count())->toBe(2);
    } finally {
        unlink($path);
        $book->disconnectWorksheets();
    }
});

it('creates and edits all sales details through the form', function () {
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class]);
    $payload = [
        'branch_id' => $this->branch->id, 'business_date' => '2026-10-16',
        'pos_reference' => '00099', 'guest_name' => 'Guest', 'address' => 'Address',
        'tin' => '000-123', 'business_source' => 'government',
        'arrival' => '2026-10-15T14:00', 'departure' => '2026-10-16T12:00',
        'vat_classification' => 'vat_exempt', 'discount_type' => 'pwd_20',
        'withholding_tax_rate' => 5, 'basic_room_rate_amount' => 1000,
        'laundry_amount' => 200, 'online_booking_amount' => 1200,
    ];
    $this->post('/pos-entries', $payload)->assertSessionHasNoErrors()->assertRedirect();
    $entry = PosEntry::firstOrFail();
    expect($entry->gross_sales)->toBe('2400.00')->and($entry->business_source)->toBe('government');
    $this->put('/pos-entries/'.$entry->id, [...$payload, 'guest_name' => 'Updated', 'laundry_amount' => 300])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($entry->refresh()->guest_name)->toBe('Updated')->and($entry->gross_sales)->toBe('2500.00');

    $this->withoutVite();
    $this->get('/pos-entries/'.$entry->id.'/edit')->assertOk()->assertInertia(fn ($page) => $page
        ->where('pos.sales_details.guest_name', 'Updated')
        ->where('pos.sales_details.business_source', 'government')
        ->where('pos.sales_details.arrival', '2026-10-15T14:00')
        ->has('mappingByBranch')
    );
});

it('requires a percentage when a manual POS entry uses an editable discount', function () {
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class]);

    $this->post('/pos-entries', [
        'branch_id' => $this->branch->id,
        'business_date' => '2026-10-16',
        'pos_reference' => 'PROMO-001',
        'discount_type' => 'promo',
    ])->assertSessionHasErrors('discount_rate');
});

it('rounds the POS VAT card from aggregate VATable sales', function () {
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class])->withoutVite();

    foreach (['VAT-001', 'VAT-002'] as $reference) {
        PosEntry::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'business_date' => '2026-10-16',
            'pos_reference' => $reference,
            'calculation_version' => 'sales_format_4',
            'vat_classification' => 'vatable',
            'taxable_amount' => '100.05',
            'vat_amount' => '12.01',
        ]);
    }

    $this->get('/pos-entries')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('totals.vat_amount', 2401));
});

it('defaults the POS list to its displayed branch and respects explicit branch filters', function () {
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class])->withoutVite();
    $gusto = $this->company->branches()->create([
        'code' => 'GUSTO', 'name' => 'Gusto', 'is_active' => true,
    ]);

    foreach ([[$this->branch, 'MAXI-ENTRY'], [$gusto, 'GUSTO-ENTRY']] as [$branch, $reference]) {
        PosEntry::create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'business_date' => '2026-10-16',
            'pos_reference' => $reference,
        ]);
    }

    $this->get('/pos-entries')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.branch_id', $gusto->id)
            ->has('entries', 1)
            ->where('entries.0.pos_reference', 'GUSTO-ENTRY')
        );

    $this->get('/pos-entries?branch_id='.$this->branch->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.branch_id', $this->branch->id)
            ->has('entries', 1)
            ->where('entries.0.pos_reference', 'MAXI-ENTRY')
        );
});

it('uploads the matching worksheet through the import endpoint', function () {
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class]);
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Lists')->setCellValue('A1', 'Business Source');
    $book->createSheet()->setTitle('Maxi')->fromArray(salesFormatRows(), null, 'A1', true);
    $book->createSheet()->setTitle('Gusto');
    $path = tempnam(sys_get_temp_dir(), 'pos-upload-');
    try {
        (new Xlsx($book))->save($path);
        $upload = new UploadedFile($path, 'sales.xlsx', null, null, true);
        $this->post('/pos-entries/import', ['branch_id' => $this->branch->id, 'file' => $upload])
            ->assertSessionHasNoErrors()->assertRedirect();
        expect(PosEntry::count())->toBe(2);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
        $book->disconnectWorksheets();
    }
});

it('saves detailed sales for reviewing the workbook journal before ledger posting', function () {
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class]);
    $payload = [
        'branch_id' => $this->branch->id, 'business_date' => '2026-10-16',
        'pos_reference' => 'DETAIL', 'basic_room_rate_amount' => 100,
        'cash_amount' => 100, 'post_now' => true,
    ];
    $this->post('/pos-entries', $payload)->assertSessionHasErrors('post_now');
    expect(PosEntry::count())->toBe(0);
});

it('rejects another company branch and invalid dropdown values', function () {
    $this->withoutMiddleware([Authenticate::class, HandleInertiaRequests::class]);
    $other = Company::create(['code' => 'OTHER', 'name' => 'Other', 'base_currency_code' => 'PHP']);
    $branch = $other->branches()->create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
    $this->post('/pos-entries', [
        'branch_id' => $branch->id, 'business_date' => '2026-10-16',
        'pos_reference' => '1', 'business_source' => 'unknown',
    ])->assertSessionHasErrors(['branch_id', 'business_source']);
    expect(PosEntry::count())->toBe(0);
});

it('resolves account codes independently for Maxi and Gusto', function () {
    $gusto = $this->company->branches()->create(['code' => 'GUSTO', 'name' => 'Gusto', 'is_active' => true]);
    $service = app(PosPostingService::class);
    $maxi = $service->resolvedConfig($this->branch);
    $other = $service->resolvedConfig($gusto);
    expect($maxi['settlement_accounts']['cash_amount'])->toBe('1101-01')
        ->and($other['settlement_accounts']['cash_amount'])->toBe('1101-02')
        ->and($other['settlement_accounts']['accounts_receivable_amount'])->toBe('1310-02')
        ->and($maxi['vat_payable_account'])->toBe('4209-01')
        ->and($other['vat_payable_account'])->toBe('4209-02')
        ->and($maxi['settlement_accounts']['card_amount'])->toBe('1316-01')
        ->and($other['settlement_accounts']['card_amount'])->toBe('1316-02');
    $service->mapping($this->company, $gusto);
});
