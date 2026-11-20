<?php

use App\Services\PosSalesCalculationService;
use Tests\TestCase;

uses(TestCase::class);

// Sheet: "Maxi (sample entry) (no OI)" — expected figures come from its JE block.
function noOi(array $data): array
{
    return app(PosSalesCalculationService::class)->calculate([...$data, 'calculation_version' => 'sales_format_5']);
}

it('computes 001236: gift voucher excluded from output VAT, 2% withholding', function () {
    $r = noOi([
        'basic_room_rate_amount' => 2588, 'early_check_in_out_amount' => 1200,
        'accounts_receivable_amount' => 720.36, 'gift_voucher_amount' => 3000,
        'vat_classification' => 'vatable', 'withholding_tax_rate' => 2, 'discount_type' => 'none',
    ]);
    expect(round($r['withholding_tax_amount']))->toBe(6764.0)
        ->and(round($r['vat_amount']))->toBe(8443.0)
        ->and(round($r['room_revenue']))->toBe(370357.0)
        ->and(round($r['amount_due']))->toBe(372036.0);
});

it('computes 001240: employee 25% discount reduces the VAT base', function () {
    $r = noOi([
        'basic_room_rate_amount' => 5976, 'vat_classification' => 'vatable', 'discount_type' => 'employee_25',
    ]);
    expect(round($r['discount_amount']))->toBe(149400.0)
        ->and(round($r['vat_amount']))->toBe(48021.0)
        ->and(round($r['room_revenue']))->toBe(549579.0)
        ->and(round($r['amount_due']))->toBe(448200.0);
});

it('treats senior and PWD as VAT-exempt even when marked VATable', function () {
    foreach (['senior_20', 'pwd_20'] as $type) {
        $r = noOi(['basic_room_rate_amount' => 2988, 'vat_classification' => 'vatable', 'discount_type' => $type]);
        expect(round($r['discount_amount']))->toBe(53357.0)
            ->and($r['vat_amount'])->toBe(0)
            ->and(round($r['room_revenue']))->toBe(266786.0)
            ->and(round($r['amount_due']))->toBe(213429.0);
    }
});

it('computes 001243: VAT exempt sale is gross divided by 1.12', function () {
    $r = noOi(['basic_room_rate_amount' => 15564, 'vat_classification' => 'vat_exempt', 'discount_type' => 'none']);
    expect(round($r['room_revenue']))->toBe(1389643.0)->and(round($r['amount_due']))->toBe(1389643.0);
});
