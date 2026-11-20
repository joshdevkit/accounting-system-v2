<?php

use App\Models\Branch;
use App\Models\PosEntry;
use App\Services\PosPostingService;
use App\Services\PosSalesCalculationService;
use Tests\TestCase;

uses(TestCase::class);

it('follows the literal workbook arithmetic for the senior citizen sample', function () {
    $result = app(PosSalesCalculationService::class)->calculate([
        'basic_room_rate_amount' => '4988.00', 'extra_bed_amount' => '1200.00',
        'card_amount' => '1200.00', 'bank_transfer_amount' => '4988.00',
        'vat_classification' => 'vatable', 'discount_type' => 'senior_20',
        'withholding_tax_rate' => 0, 'complimentary_room_amount' => 500,
    ]);
    expect($result['gross_sales'])->toBe(1237600)
        ->and($result['taxable_amount'])->toBe(1105000)
        ->and($result['exempted_amount'])->toBe(552500)
        ->and($result['vat_amount'])->toBe(132600)
        ->and($result['discount_amount'])->toBe(110500)
        ->and($result['amount_due'])->toBe(1679600);
});

it('includes complementary rooms in the accountant-format gross sales', function () {
    // Workbook row 001238: complementary room N 2,588.00 with no other income.
    $calculator = app(PosSalesCalculationService::class);
    $result = $calculator->calculate([
        'calculation_version' => 'sales_format_3',
        'vat_classification' => 'vatable', 'discount_type' => 'none',
        'complimentary_room_amount' => '2588.00',
    ]);
    expect($result['gross_sales'])->toBe(258800)
        ->and($result['amount_due'])->toBe(258800);
});

it('calculates editable discounts and withholding from the displayed bases', function () {
    // Accountant format: gross is income only (payments are tenders, not sales).
    $result = app(PosSalesCalculationService::class)->calculate([
        'calculation_version' => 'sales_format_3',
        'basic_room_rate_amount' => 1120, 'cash_amount' => 1120,
        'vat_classification' => 'non_vatable', 'discount_type' => 'corporate_government',
        'discount_rate' => 10, 'withholding_tax_rate' => 2,
    ]);
    expect($result['taxable_amount'])->toBe(112000)
        ->and($result['vat_amount'])->toBe(0)
        ->and($result['discount_amount'])->toBe(11200)
        ->and($result['withholding_tax_amount'])->toBe(2240)
        ->and($result['amount_due'])->toBe(98560);
});

it('applies editable final-format discounts to VAT-exempt sales', function () {
    $result = app(PosSalesCalculationService::class)->calculate([
        'calculation_version' => 'sales_format_4',
        'basic_room_rate_amount' => 1120,
        'cash_amount' => 1120,
        'vat_classification' => 'vat_exempt',
        'discount_type' => 'promo',
        'discount_rate' => 10,
    ]);

    expect($result['exempted_amount'])->toBe(100000)
        ->and($result['discount_rate'])->toBe(10.0)
        ->and($result['discount_amount'])->toBe(10000)
        ->and($result['amount_due'])->toBe(90000);
});

it('keeps a non-VATable Maxi-format invoice at its full gross amount', function () {
    // Workbook row 001245: Non-VATable 3,288.00 must not be divided by 1.12.
    $calculator = app(PosSalesCalculationService::class);
    $result = $calculator->calculate([
        'basic_room_rate_amount' => '3288.00', 'card_amount' => '3288.00',
        'vat_classification' => 'non_vatable', 'discount_type' => 'none',
        'calculation_version' => 'sales_format_3',
    ]);
    expect($result['gross_sales'])->toBe(328800)
        ->and($result['taxable_amount'])->toBe(328800)
        ->and($result['vat_amount'])->toBe(0)
        ->and($result['amount_due'])->toBe(328800);
});

it('keeps missing editable rates distinct from an explicit zero rate', function () {
    $calculator = app(PosSalesCalculationService::class);
    $data = ['basic_room_rate_amount' => 1120, 'discount_type' => 'promo'];
    expect($calculator->calculate($data)['missing_inputs'])->toBe(['Discount percentage'])
        ->and($calculator->calculate([...$data, 'discount_rate' => 0])['missing_inputs'])->toBe([]);
});

it('uses fixed employee and pwd percentages and conditional VAT', function () {
    $calculator = app(PosSalesCalculationService::class);
    $data = ['basic_room_rate_amount' => 1120, 'cash_amount' => 1120, 'discount_type' => 'employee_25', 'discount_rate' => 99];
    $employee = $calculator->calculate($data);
    $pwd = $calculator->calculate([...$data, 'discount_type' => 'pwd_20', 'vat_classification' => 'vatable']);
    expect($employee['discount_rate'])->toBe(25.0)
        ->and($employee['discount_amount'])->toBe(50000)
        ->and($employee['vat_amount'])->toBe(0)
        ->and($pwd['exempted_amount'])->toBe(100000)
        ->and($pwd['discount_amount'])->toBe(20000)
        ->and($pwd['vat_amount'])->toBe(24000);
});

it('replaces journal placeholders with input amounts on the workbook sides', function () {
    $pos = new PosEntry([
        'basic_room_rate_amount' => 4988, 'extra_bed_amount' => 1200,
        'card_amount' => 1200, 'bank_transfer_amount' => 4988,
        'vat_classification' => 'vatable', 'discount_type' => 'senior_20',
        'gift_voucher_amount' => 88,
    ]);
    $pos->setRelation('branch', new Branch(['code' => 'MAXI']));
    $preview = app(PosPostingService::class)->workbookPreview($pos);
    $rows = collect($preview['rows'])->keyBy('label');
    expect($rows['Debit/Credit Card Sales']['amount'])->toBe(120000)
        ->and($rows['Cash in Bank']['amount'])->toBe(498800)
        ->and($rows['Discount - Senior Citizen']['side'])->toBe('credit')
        ->and($rows['Discount - Senior Citizen']['amount'])->toBe(110500)
        ->and($rows['Unearned Revenue - Gift Voucher']['side'])->toBe('credit')
        ->and($rows['Unearned Revenue - Gift Voucher']['amount'])->toBe(8800)
        ->and($rows['Room Revenue']['amount'])->toBe(618800)
        ->and($preview['difference'])->not->toBe(0)
        ->and($rows->has('Cash over / short'))->toBeFalse();
});

it('calculates supplier amounts from AVL gross less the entered increments', function () {
    $pos = new PosEntry(['avl_amount' => 30000, 'avl_increment_amount' => 5000, 'massage_increment_amount' => 0]);
    $pos->setRelation('branch', null);
    $preview = app(PosPostingService::class)->workbookPreview($pos);
    $rows = collect($preview['rows'])->keyBy('label');
    expect($rows['Accounts Payable - AVL']['amount'])->toBe(2500000)
        ->and($rows['Accounts Payable - Massage Services']['amount'])->toBe(3000000)
        ->and($rows['Accounts Payable - Other Suppliers']['amount'])->toBeNull()
        ->and($preview['complete'])->toBeFalse();
});
