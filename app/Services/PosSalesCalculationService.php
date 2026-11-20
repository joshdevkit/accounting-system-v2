<?php

namespace App\Services;

use App\Support\Money;

class PosSalesCalculationService
{
    public function calculate(array $data): array
    {
        $sum = fn (array $fields): int => Money::sum(array_map(fn (string $field) => $data[$field] ?? 0, $fields));
        $room = $sum(config('accounting.pos.room_fields'));
        $income = $sum(array_keys(config('accounting.pos.income_fields')));
        $latest = in_array($data['calculation_version'] ?? null, ['sales_format_3', 'sales_format_4'], true);
        $final = ($data['calculation_version'] ?? null) === 'sales_format_4';
        if ($latest) {
            $income += $sum(config('accounting.pos.increment_fields'));
        }
        $payments = $sum(config('accounting.pos.payment_fields'));
        // The accountant workbook sums I–AA, so the complementary-room column N
        // is part of gross sales there; legacy files keep excluding it.
        $complimentary = Money::cents($data['complimentary_room_amount'] ?? 0);
        $gross = $income + ($latest ? $complimentary : $payments);
        $discountType = $data['discount_type'] ?? null;
        $round = fn (float $value) => $latest
            ? (abs($value - round($value)) < 0.000000001 ? (int) round($value) : $value)
            : (int) round($value);
        $exempt = $final && in_array($data['vat_classification'] ?? null, ['non_vatable', 'vat_exempt'], true)
            ? $round($gross / 1.12)
            : (in_array($discountType, ['senior_20', 'pwd_20'], true) ? $round($room / 1.12) : 0);
        $vatable = ($data['vat_classification'] ?? null) === 'vatable';
        // Sales Format_Final places Non-VATable sales in the exempt column.
        $taxable = $latest
            ? ($final && ! $vatable ? 0 : ($vatable ? $gross / 1.12 : $gross))
            : (int) round($gross / 1.12);
        $vat = $vatable ? $round($taxable * 0.12) : 0;
        $discountRate = $discountType === null || $discountType === '' ? 0 : config('accounting.pos.discount_rates.'.$discountType);
        $discountRate ??= $data['discount_rate'] ?? null;
        $discountBase = in_array($discountType, ['senior_20', 'pwd_20'], true)
            || ($final && ! $vatable)
            ? $exempt
            : $taxable;
        $discount = $round($discountBase * (float) ($discountRate ?? 0) / 100);
        $withholding = $round($taxable * (float) ($data['withholding_tax_rate'] ?? 0) / 100);
        // The original accountant format keeps Non-VATable sales at gross;
        // the Final sheet uses the exempt-column value for the due amount.
        $due = $final && ! $vatable
            ? $exempt - $discount - $withholding
            : ($latest && ! $vatable
                ? $gross - $discount - $withholding
                : $taxable + $exempt + $vat - $discount - $withholding);
        if (abs($due - round($due)) < 0.000000001) {
            $due = (int) round($due);
        }
        // Net sales is the taxable/exempt revenue already reduced by discounts.
        // A Non-VATable accountant-format invoice carries its whole gross in the
        // taxable column, so adding the informational exempt column would
        // double-count a statutory discount.
        $net = $final && ! $vatable
            ? $exempt - $discount
            : ($latest && ! $vatable
                ? $gross - $discount
                : $taxable + $exempt - $discount);

        return [
            'calculation_version' => $final ? 'sales_format_4' : ($latest ? 'sales_format_3' : 'sales_format_2'),
            'income_total' => $income,
            'room_total' => $room,
            'payments_total' => $payments,
            'gross_sales' => $gross,
            'taxable_amount' => $taxable,
            'exempted_amount' => $exempt,
            'vat_amount' => $vat,
            'discount_amount' => $discount,
            'withholding_tax_amount' => $withholding,
            'amount_due' => $due,
            'net_sales' => $net,
            'discount_rate' => $discountRate === null ? null : (float) $discountRate,
            'discount_base' => $discountBase,
            'withholding_base' => $taxable,
            'missing_inputs' => $discountRate === null ? ['Discount percentage'] : [],
        ];
    }

    public function attributes(array $data): array
    {
        $calculated = $this->calculate($data);
        $attributes = [];
        foreach (['gross_sales', 'taxable_amount', 'exempted_amount', 'vat_amount', 'discount_amount', 'withholding_tax_amount', 'amount_due'] as $field) {
            $attributes[$field] = Money::decimal((int) round($calculated[$field]));
        }
        $attributes['net_sales'] = Money::decimal((int) round($calculated['net_sales']));
        $attributes['calculation_version'] = $calculated['calculation_version'];

        return $attributes;
    }
}
