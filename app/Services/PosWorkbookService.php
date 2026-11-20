<?php

namespace App\Services;

use App\Models\PosEntry;
use App\Support\Money;

class PosWorkbookService
{
    public function preview(PosEntry $pos): array
    {
        if ($pos->calculation_version === 'sales_format_5') {
            return $this->noOtherIncomePreview($pos);
        }
        if ($pos->calculation_version === 'sales_format_4') {
            return $this->finalPreview($pos);
        }
        $sales = app(PosSalesCalculationService::class)->calculate($pos->getAttributes());
        $config = app(PosPostingService::class)->resolvedConfig($pos->branch);
        $suffix = config('accounting.branch_suffixes.'.$pos->branch?->code);
        $resolve = fn (mixed $code) => is_array($code) ? ($code[$suffix ?? ''] ?? null) : $code;
        $amount = fn (string $field): int => Money::cents($pos->{$field});
        $accounts = $config['workbook_accounts'];
        $rows = [];
        $append = function (string $label, string $side, ?float $cents, mixed $code, string $basis) use (&$rows, $resolve): void {
            $rows[] = ['label' => $label, 'side' => $side, 'amount' => $cents, 'code' => $resolve($code), 'basis' => $basis];
        };
        $append('Cash on Hand', 'debit', $amount('cash_amount'), $config['settlement_accounts']['cash_amount'], 'Cash payment');
        $append('Debit/Credit Card Sales', 'debit', $amount('card_amount'), $config['settlement_accounts']['card_amount'], 'Card payment');
        $append('Cash in Bank', 'debit', $amount('bank_transfer_amount') + $amount('gcash_qrph_amount') + $amount('online_booking_amount'), $config['settlement_accounts']['bank_transfer_amount'], 'Bank transfer, QR Ph, online booking');
        $append('Accounts Receivable', 'debit', $amount('accounts_receivable_amount'), $config['settlement_accounts']['accounts_receivable_amount'], 'Send bill');
        $append('Unearned Revenue - Gift Voucher', 'debit', $amount('gift_voucher_amount'), $accounts['gift_voucher'], 'Gift voucher payment');
        foreach (['cheque_amount', 'room_post_amount', 'other_payment_amount'] as $field) {
            if ($amount($field) !== 0) {
                $append($config['payment_labels'][$field], 'debit', $amount($field), $config['settlement_accounts'][$field], 'Additional payment entered on this invoice');
            }
        }
        $journalGross = $sales['income_total'] + $amount('complimentary_room_amount');
        $append('Creditable Withholding Tax', 'debit', $journalGross / 1.12 * (float) $pos->withholding_tax_rate / 100, $config['withholding_tax_account'], 'Sum I–AA ÷ 1.12 × withholding rate');
        $append('Cost of Goods Sold - Key Card', 'debit', $amount('key_card_amount'), $accounts['key_card_cost'], 'Key card actual cost');
        $append('Cost of Goods Sold - Amenities', 'debit', $amount('amenities_amount'), $accounts['amenities_cost'], 'Amenities actual cost');
        $vat = $pos->vat_classification === 'vatable' ? ($journalGross - $amount('gift_voucher_amount')) / 1.12 * 0.12 : 0;
        $append('Output Tax', 'credit', $vat, $config['vat_payable_account'], '(Sum I–AA − gift voucher) ÷ 1.12 × 12%, when VATable');
        foreach (
            [
                'senior_20' => 'Discount - Senior Citizen',
                'employee_25' => 'Discount - Employee',
                'pwd_20' => 'Discount - PWD',
                'promo' => 'Promo Discount',
                'special_open' => 'Special Open Discount',
                'corporate_government' => "Discount - Corporate & Gov't",
            ] as $type => $label
        ) {
            $value = $pos->discount_type === $type ? ($sales['discount_rate'] === null ? null : $sales['discount_amount']) : 0;
            $append($label, 'credit', $value, $config['discount_accounts'][$type], 'Selected discount');
        }
        foreach (
            [
                ['massage_increment_amount', 'spa_amount', 'SPA'],
                ['laundry_amount', 'laundry_amount', 'Laundry Bag & Laundry Services'],
                ['shuttle_amount', 'shuttle_amount', 'Shuttle Services'],
                ['smoking_fee_amount', 'smoking_fee_amount', 'Smoking Fee'],
                ['key_card_increment_amount', 'key_card_amount', 'Key Card'],
                ['amenities_increment_amount', 'amenities_amount', 'Amenities'],
                ['avl_increment_amount', 'avl_amount', 'AVL'],
                ['other_suppliers_increment_amount', 'other_income_amount', 'Others'],
            ] as [$field, $key, $label]
        ) {
            $append('Other Income - '.$label, 'credit', $amount($field), $accounts[$key], 'Invoice amount: '.$field);
        }
        $append('Room Revenue', 'credit', $sales['room_total'] + $amount('complimentary_room_amount') - $amount('gift_voucher_amount'), $config['revenue_account'], 'Room charges I–N − gift voucher');
        foreach (
            [
                ['spa_amount', 'massage_payable', 'Accounts Payable - Massage Service'],
                ['avl_amount', 'avl_payable', 'Accounts Payable - AVL Service'],
                ['other_income_amount', 'other_suppliers_payable', 'Accounts Payable - Other Suppliers'],
                ['key_card_amount', 'key_card_inventory', 'Inventory - Key Card'],
                ['amenities_amount', 'amenities_inventory', 'Inventory - Amenities'],
            ] as [$field, $key, $label]
        ) {
            $append($label, 'credit', $amount($field), $accounts[$key], 'Actual cost: '.$field);
        }
        $debits = array_sum(array_map(fn (array $row) => $row['side'] === 'debit' ? ($row['amount'] ?? 0) : 0, $rows));
        $credits = array_sum(array_map(fn (array $row) => $row['side'] === 'credit' ? ($row['amount'] ?? 0) : 0, $rows));

        return [
            'sales' => $sales,
            'rows' => $rows,
            'debits' => $debits,
            'credits' => $credits,
            'difference' => $debits - $credits,
            'complete' => ! in_array(null, array_column($rows, 'amount'), true),
        ];
    }

    /**
     * Draft journal for "Maxi (sample entry) (no OI)". Discounts sit on the DEBIT side here.
     */
    protected function noOtherIncomePreview(PosEntry $pos): array
    {
        $sales = app(PosSalesCalculationService::class)->calculate($pos->getAttributes());
        $config = app(PosPostingService::class)->resolvedConfig($pos->branch);
        $suffix = config('accounting.branch_suffixes.'.$pos->branch?->code);
        $resolve = fn (mixed $code) => is_array($code) ? ($code[$suffix ?? ''] ?? null) : $code;
        $money = fn (string $field): int => Money::cents($pos->{$field});
        $accounts = $config['workbook_accounts'];
        $rows = [];
        $append = function (string $label, string $side, ?float $cents, mixed $code, string $basis) use (&$rows, $resolve): void {
            $rows[] = ['label' => $label, 'side' => $side, 'amount' => $cents, 'code' => $resolve($code), 'basis' => $basis];
        };
        $append('Cash on Hand', 'debit', $money('cash_amount'), $config['settlement_accounts']['cash_amount'], 'Cash payment');
        $append('Debit/Credit Card', 'debit', $money('card_amount'), $config['settlement_accounts']['card_amount'], 'Card payment');
        $append('Cash in Bank', 'debit', $money('bank_transfer_amount') + $money('gcash_qrph_amount') + $money('online_booking_amount'), $config['settlement_accounts']['bank_transfer_amount'], 'Bank transfer, QR Ph, online booking');
        $append('Accounts Receivable', 'debit', $money('accounts_receivable_amount'), $config['settlement_accounts']['accounts_receivable_amount'], 'Send bill');
        $append('Receivable from Employee - Salary Deduction', 'debit', $money('ar_employee_amount'), $config['settlement_accounts']['ar_employee_amount'], 'AR Employees');
        $append('Unearned Revenue - Gift Voucher', 'debit', $money('gift_voucher_amount'), $accounts['gift_voucher'], 'Gift voucher amount as entered');
        foreach (['cheque_amount', 'room_post_amount', 'other_payment_amount'] as $field) {
            if ($money($field) !== 0) {
                $append($config['payment_labels'][$field], 'debit', $money($field), $config['settlement_accounts'][$field], 'Additional payment entered on this invoice');
            }
        }
        foreach ([
            'senior_20' => 'Discount - Senior Citizen', 'employee_25' => 'Discount - Employee',
            'pwd_20' => 'Discount - PWD', 'promo' => 'Promo Discount',
            'special_open' => 'Special Open Discount', 'corporate_government' => "Discount - Corporate & Gov't",
        ] as $type => $label) {
            $value = $pos->discount_type === $type ? ($sales['discount_rate'] === null ? null : $sales['discount_amount']) : 0;
            $append($label, 'debit', $value, $config['discount_accounts'][$type], 'Selected discount');
        }
        $append('Creditable Withholding Tax', 'debit', $sales['withholding_tax_amount'], $config['withholding_tax_account'], 'Room gross ÷ 1.12 × withholding percentage');
        $append('Output Tax', 'credit', $sales['vat_amount'], $config['vat_payable_account'], '(Room gross − gift voucher − discount) ÷ 1.12 × 12%, VATable only');
        $append('Room Revenue', 'credit', $sales['room_revenue'], $config['revenue_account'], 'VATable: room gross − output tax. Senior/PWD, Non-VATable, VAT Exempt: room gross ÷ 1.12');
        $debits = array_sum(array_map(fn (array $row) => $row['side'] === 'debit' ? ($row['amount'] ?? 0) : 0, $rows));
        $credits = array_sum(array_map(fn (array $row) => $row['side'] === 'credit' ? ($row['amount'] ?? 0) : 0, $rows));

        return [
            'sales' => $sales, 'rows' => $rows, 'debits' => $debits, 'credits' => $credits,
            'difference' => $debits - $credits,
            'complete' => ! in_array(null, array_column($rows, 'amount'), true),
        ];
    }

    protected function finalPreview(PosEntry $pos): array
    {
        $sales = app(PosSalesCalculationService::class)->calculate($pos->getAttributes());
        $config = app(PosPostingService::class)->resolvedConfig($pos->branch);
        $suffix = config('accounting.branch_suffixes.'.$pos->branch?->code);
        $resolve = fn (mixed $code) => is_array($code) ? ($code[$suffix ?? ''] ?? null) : $code;
        $money = fn (string $field): int => Money::cents($pos->{$field});
        $accounts = $config['workbook_accounts'];
        $rows = [];
        $append = function (string $label, string $side, ?float $cents, mixed $code, string $basis) use (&$rows, $resolve): void {
            $rows[] = ['label' => $label, 'side' => $side, 'amount' => $cents, 'code' => $resolve($code), 'basis' => $basis];
        };
        $append('Cash on Hand', 'debit', $money('cash_amount'), $config['settlement_accounts']['cash_amount'], 'Cash payment');
        $append('Debit/Credit Card', 'debit', $money('card_amount'), $config['settlement_accounts']['card_amount'], 'Card payment');
        $append('Cash in Bank', 'debit', $money('bank_transfer_amount') + $money('gcash_qrph_amount') + $money('online_booking_amount'), $config['settlement_accounts']['bank_transfer_amount'], 'Bank transfer, QR, online booking');
        $append('Accounts Receivable', 'debit', $money('accounts_receivable_amount'), $config['settlement_accounts']['accounts_receivable_amount'], 'Send bill');
        $giftBase = $money('gift_voucher_amount');
        $append('Unearned Revenue - Gift Voucher', 'debit', $giftBase / 1.12, $accounts['gift_voucher'], 'Gift voucher amount ÷ 1.12');
        foreach (['cheque_amount', 'room_post_amount', 'other_payment_amount'] as $field) {
            if ($money($field) !== 0) {
                $append($config['payment_labels'][$field], 'debit', $money($field), $config['settlement_accounts'][$field], 'Additional payment entered on this invoice');
            }
        }
        $vatable = $pos->vat_classification === 'vatable';
        $incomeGross = $sales['income_total'] + $money('complimentary_room_amount');
        $withholding = $incomeGross / 1.12 * (float) $pos->withholding_tax_rate / 100;
        $append('Creditable Withholding Tax', 'debit', $withholding, $config['withholding_tax_account'], 'Total sales gross ÷ 1.12 × withholding percentage');
        $vat = $vatable ? ($incomeGross / 1.12 * 0.12) - ($giftBase / 1.12 * 0.12) : 0;
        $append('Output Tax', 'credit', $vat, $config['vat_payable_account'], '12% VAT on gross sales less VAT on gift vouchers');
        foreach ([
            'senior_20' => 'Discount - Senior Citizen', 'employee_25' => 'Discount - Employee',
            'pwd_20' => 'Discount - PWD', 'promo' => 'Promo Discount',
            'special_open' => 'Special Open Discount', 'corporate_government' => "Discount - Corporate & Gov't",
        ] as $type => $label) {
            $discount = $pos->discount_type === $type ? $sales['discount_amount'] : 0;
            $append($label, 'credit', $discount, $config['discount_accounts'][$type], 'Discount percentage');
        }
        $append('Room Revenue - Complementary Room', 'credit', 0, $accounts['complimentary_room'], 'Complementary room amount is not assigned a nonzero JE formula on the sample sheet');
        $netService = fn (string $actual, string $increment, bool $supplier): float => $money($actual) + $money($increment)
            - ($vatable ? (($money($actual) + $money($increment)) / 1.12 * 0.12) : 0)
            - ($supplier ? $money($actual) : 0);
        $services = [
            ['Other Income - SPA', 'spa_amount', 'massage_increment_amount', true, $accounts['spa_amount'], 'Accounts Payable - Massage Service', $accounts['massage_payable']],
            ['Other Income - Laundry Bag & Laundry Services', 'laundry_amount', 'laundry_increment_amount', true, $accounts['laundry_amount'], 'Inventory - Laundry Bag', $accounts['laundry_inventory']],
            ['Other Income - Shuttle Services', 'shuttle_amount', null, false, $accounts['shuttle_amount']],
            ['Other Income - Smoking Fee', 'smoking_fee_amount', null, false, $accounts['smoking_fee_amount']],
            ['Other Income - Key Card', 'key_card_amount', 'key_card_increment_amount', true, $accounts['key_card_amount'], 'Inventory - Key Card', $accounts['key_card_inventory']],
            ['Other Income - Amenities', 'amenities_amount', 'amenities_increment_amount', true, $accounts['amenities_amount'], 'Inventory - Amenities', $accounts['amenities_inventory']],
            ['Other Income - AVL', 'avl_amount', 'avl_increment_amount', true, $accounts['avl_amount'], 'Accounts Payable - AVL Service', $accounts['avl_payable']],
            ['Other Income - Others', 'other_income_amount', 'other_suppliers_increment_amount', true, $accounts['other_income_amount'], 'Accounts Payable - Other Suppliers', $accounts['other_suppliers_payable']],
        ];
        foreach ($services as $service) {
            [$label, $actualField, $incrementField, $hasSupplier, $incomeCode, $costLabel, $costCode] = array_pad($service, 7, null);
            $incrementField ??= $actualField.'_zero_increment';
            $append($label, 'credit', $netService($actualField, $incrementField, $hasSupplier), $incomeCode, 'Gross service charges less output VAT and actual supplier cost');
            if (isset($costLabel)) {
                $append($costLabel, 'credit', $money($actualField), $costCode, 'Actual cost');
            }
        }
        $roomRevenue = ($sales['room_total'] / 1.12);
        $append('Room Revenue', 'credit', $roomRevenue, $config['revenue_account'], 'Basic room rates plus extra bed, upgrade, no show and early check-in/out ÷ 1.12');
        $debits = array_sum(array_map(fn (array $row) => $row['side'] === 'debit' ? ($row['amount'] ?? 0) : 0, $rows));
        $credits = array_sum(array_map(fn (array $row) => $row['side'] === 'credit' ? ($row['amount'] ?? 0) : 0, $rows));

        return [
            'sales' => $sales, 'rows' => $rows, 'debits' => $debits, 'credits' => $credits,
            'difference' => $debits - $credits,
            'complete' => ! in_array(null, array_column($rows, 'amount'), true),
        ];
    }
}
