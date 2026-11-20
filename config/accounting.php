<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fiscal years and accounting periods
    |--------------------------------------------------------------------------
    */

    'fiscal_years' => [
        'periods' => (int) env('ACCOUNTING_PERIODS_PER_YEAR', 12),
        'auto_generate_periods' => (bool) env('ACCOUNTING_AUTO_PERIODS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal entries
    |--------------------------------------------------------------------------
    */

    'journal_entries' => [
        'reference_prefix' => env('ACCOUNTING_JE_PREFIX', 'JE'),
        'reference_padding' => 5,
        'max_lines' => 50,
    ],

    'branch_suffixes' => [
        'MAXI' => '01',
        'GUSTO' => '02',
    ],

    /*
    |--------------------------------------------------------------------------
    | POS day-summary posting
    |--------------------------------------------------------------------------
    */

    'pos' => [
        'increment_fields' => [
            'massage_increment_amount', 'laundry_increment_amount', 'key_card_increment_amount', 'amenities_increment_amount',
            'avl_increment_amount', 'other_suppliers_increment_amount',
        ],
        'room_fields' => [
            'basic_room_rate_amount', 'extra_bed_amount', 'room_upgrade_amount',
            'no_show_amount', 'early_check_in_out_amount',
        ],
        'discount_rates' => ['none' => 0, 'senior_20' => 20, 'pwd_20' => 20, 'employee_25' => 25],
        'income_fields' => [
            'basic_room_rate_amount' => 'Basic room rates',
            'extra_bed_amount' => 'Extra bed',
            'room_upgrade_amount' => 'Room upgrade',
            'no_show_amount' => 'No show',
            'early_check_in_out_amount' => 'Early check-in/check-out',
            'spa_amount' => 'SPA',
            'laundry_amount' => 'Laundry bag & laundry services',
            'shuttle_amount' => 'Shuttle services',
            'smoking_fee_amount' => 'Smoking fee',
            'key_card_amount' => 'Key card',
            'amenities_amount' => 'Amenities (pillows, robes, etc)',
            'avl_amount' => 'AVL (audio, visual, and lighting)',
            'other_income_amount' => 'Others (flowers, slippers, cake, chocolates, balloons, etc)',
        ],

        'business_sources' => [
            'individual' => 'Individual',
            'government' => 'Government',
            'corporate' => 'Corporate',
            'website_online_booking' => 'Website/Online Booking',
        ],
        'vat_classifications' => [
            'vatable' => 'VATable Sales',
            'non_vatable' => 'Non-VATable Sales',
            'vat_exempt' => 'VAT Exempt Sales',
        ],
        'discount_types' => [
            'none' => 'None',
            'senior_20' => '20% - Senior Citizen',
            'employee_25' => '25% - Employee',
            'pwd_20' => '20% - PWD',
            'promo' => 'Promo Discount',
            'special_open' => 'Special Open Discount',
            'corporate_government' => "Corporate & Gov't Discount",
        ],
        'withholding_tax_rates' => [0, 1, 2, 5, 6, 7],

        'amount_fields' => [
            'massage_increment_amount', 'laundry_increment_amount', 'key_card_increment_amount', 'amenities_increment_amount',
            'avl_increment_amount', 'other_suppliers_increment_amount',
            'complimentary_room_amount',
            'amount_due',
            'withholding_tax_amount',
            'basic_room_rate_amount',
            'extra_bed_amount',
            'room_upgrade_amount',
            'no_show_amount',
            'early_check_in_out_amount',
            'spa_amount',
            'laundry_amount',
            'shuttle_amount',
            'smoking_fee_amount',
            'key_card_amount',
            'amenities_amount',
            'avl_amount',
            'other_income_amount',
            'gross_sales',
            'discount_amount',
            'taxable_amount',
            'exempted_amount',
            'vat_amount',
            'local_tax_amount',
            'service_charge_amount',
            'net_sales',
        ],

        'payment_fields' => [
            'online_booking_amount',
            'cash_amount',
            'card_amount',
            'gcash_qrph_amount',
            'bank_transfer_amount',
            'cheque_amount',
            'accounts_receivable_amount',
            'ar_employee_amount',
            'room_post_amount',
            'gift_voucher_amount',
            'other_payment_amount',
        ],

        'settlement_accounts' => [
            'cash_amount' => ['01' => '1101-01', '02' => '1101-02'],
            'card_amount' => ['01' => '1316-01', '02' => '1316-02'],
            'gcash_qrph_amount' => ['01' => '1102-01', '02' => '1102-02'],
            'bank_transfer_amount' => ['01' => '1102-01', '02' => '1102-02'],
            'cheque_amount' => ['01' => '1107-01', '02' => '1107-02'],
            'accounts_receivable_amount' => ['01' => '1301-01', '02' => '1310-02'],
            'ar_employee_amount' => ['01' => '1402-01', '02' => '1402-02'],
            'room_post_amount' => ['01' => '1301-01', '02' => '1314-02'],
            'online_booking_amount' => ['01' => '1312-01', '02' => '1312-02'],
            'gift_voucher_amount' => ['01' => '1311-01', '02' => '1311-02'],
            'other_payment_amount' => null,
        ],
        'payment_labels' => [
            'cash_amount' => 'Cash',
            'card_amount' => 'Card payment',
            'gcash_qrph_amount' => 'GCash / QRPh',
            'bank_transfer_amount' => 'Bank transfer, QR Ph, online booking',
            'cheque_amount' => 'Cheque',
            'accounts_receivable_amount' => 'Send bill',
            'ar_employee_amount' => 'AR Employees',
            'room_post_amount' => 'Room post',
            'online_booking_amount' => 'Online booking',
            'gift_voucher_amount' => 'Gift voucher',
            'other_payment_amount' => 'Other payment',
        ],
        'revenue_account' => ['01' => '8901-01', '02' => '8901-02'],
        'discount_account' => null,
        'discount_accounts' => [
            'senior_20' => ['01' => '8905-01', '02' => '8913-02'],
            'pwd_20' => ['01' => '8906-01', '02' => '8915-02'],
            'employee_25' => ['01' => '8907-01', '02' => '8917-02'],
            'promo' => ['01' => '8908-01', '02' => '8919-02'],
            'special_open' => ['01' => '8909-01', '02' => null],
            'corporate_government' => null,
        ],
        'workbook_accounts' => [
            'key_card_cost' => ['01' => '9002-01', '02' => null],
            'amenities_cost' => ['01' => '9002-01', '02' => null],
            'key_card_inventory' => ['01' => '1502-01', '02' => null],
            'amenities_inventory' => ['01' => '1502-01', '02' => null],
            'laundry_inventory' => ['01' => '1502-01', '02' => null],
            'other_income_amount' => ['01' => '13102-01', '02' => '13102-02'],
            'complimentary_room' => null,
            'gift_voucher' => null,
            'spa_amount' => null,
            'laundry_amount' => null,
            'shuttle_amount' => null,
            'smoking_fee_amount' => ['01' => '13105-01', '02' => '13105-02'],
            'key_card_amount' => null,
            'amenities_amount' => null,
            'avl_amount' => null,
            'avl_payable' => null,
            'other_suppliers_payable' => null,
            'massage_payable' => null,
        ],
        'vat_payable_account' => ['01' => '4209-01', '02' => '4209-02'],
        'withholding_tax_account' => ['01' => '1607-01', '02' => '1607-02'],
        'local_tax_payable_account' => null,
        'service_charge_payable_account' => ['01' => null, '02' => '4105-02'],
        'over_short_account' => ['01' => '13101-01', '02' => '13101-02'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Expenses
    |--------------------------------------------------------------------------
    */

    'expenses' => [

        'input_vat_account' => ['01' => '1160', '02' => '1160'],
        'withholding_tax_payable_account' => ['01' => '2110', '02' => '2110'],

        'vat_rate' => 0.12,

        'withholding_tax_rates' => [
            '0' => 'None',
            '1' => '1%',
            '2' => '2%',
            '5' => '5%',
        ],

        'settlement_accounts' => [
            'cash' => '1101',
            'bank_transfer' => '1102',
            'gcash_qrph' => '1101',
            'card' => '1106',
            'cheque' => '1107',
        ],
        // The final accountant-approved workbook uses Accounts Payable -
        // Operations as the standard expense payable account for both units.
        'payable_account' => ['01' => '4101-01', '02' => '4101-02'],

        'settlement_labels' => [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank transfer',
            'gcash_qrph' => 'GCash / QRPh',
            'card' => 'Card',
            'cheque' => 'Cheque',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Purchases
    |--------------------------------------------------------------------------
    */

    'purchases' => [
        'input_vat_account' => '1608',
        'withholding_tax_payable_account' => '4207',
        'payable_account' => '4101',
        'affiliate_due_to_account' => '4201',
        'clearing_account' => ['01' => '4107-01', '02' => '4107-02'],
        'vat_rate' => 0.12,

        'withholding_tax_rates' => [
            '0' => 'None',
            '1' => '1%',
            '2' => '2%',
            '5' => '5%',
        ],

        'settlement_accounts' => [
            'cash' => '1101',
            'bank_transfer' => ['01' => '1102-01', '02' => '4201-02'],
            'gcash_qrph' => '1101',
            'card' => '1106',
            // Cheques clear straight into the branch's CBC account; the old
            // "Check For Deposit" transit account no longer exists per-branch.
            'cheque' => ['01' => '1103-01', '02' => '1103-02'],
        ],

        'settlement_labels' => [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank transfer',
            'gcash_qrph' => 'GCash / QRPh',
            'card' => 'Card',
            'cheque' => 'Cheque',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sales
    |--------------------------------------------------------------------------
    */

    'sales' => [
        'output_vat_account' => '4209',
        'withholding_tax_receivable_account' => '1607',
        'receivable_account' => '1301',

        'vat_rate' => 0.12,

        'withholding_tax_rates' => [
            '0' => 'None',
            '1' => '1%',
            '2' => '2%',
            '5' => '5%',
            '6' => '6%',
            '7' => '7%',
        ],

        'business_sources' => [
            'individual' => 'Individual',
            'government' => 'Government',
            'corporate' => 'Corporate',
            'website_online_booking' => 'Website/Online Booking',
        ],

        'discounts' => [
            'none' => 'None',
            'senior_20' => '20% - Senior Citizen',
            'employee_25' => '25% - Employee',
            'pwd_20' => '20% - PWD',
        ],

        'settlement_accounts' => [
            'cash' => '1101',
            'bank_transfer' => '1102',
            'gcash_qrph' => '1101',
            'card' => '1106',
        ],

        'settlement_labels' => [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank transfer',
            'gcash_qrph' => 'GCash / QRPh',
            'card' => 'Card',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard balances
    |--------------------------------------------------------------------------
    */

    'dashboard' => [
        'cash_accounts' => [
            '1101-01',
            '1102-01',
            '1103-01',
            '1104-01',
            '1105-01',
            '1106-01',
            '1107-01',
            '1201-01',
            '1202-01',
            '1203-01',
        ],

        'receivable_accounts' => [
            '1301-01',
            '1302-01',
            '1303-01',
            '1304-01',
            '1305-01',
            '1306-01',
            '1307-01',
            '1308-01',
            '1309-01',
            '1310-01',
            '1311-01',
            '1312-01',
        ],
    ],

];
