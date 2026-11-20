<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds the accounts from "Maxi Working Paper 2026" that
 * ChartOfAccountsSeeder did not load. Run this AFTER ChartOfAccountsSeeder —
 * it looks up several existing groups (1100, 4100) by code and hangs new
 * leaves off them, and it re-touches the AccountType table.
 *
 *   -01 = Maximilian      -02 = Gusto
 *
 * What this seeder adds:
 *
 *  1. A few leaves ChartOfAccountsSeeder missed under existing groups:
 *     1100 Cash            -> 1106-02, 1107-02 (Gusto side of BPI / Check for
 *                             Deposit, which only had a Maximilian leaf)
 *     4100 Payable Trade    -> 4104-01 Accounts Payable - Others (Maximilian)
 *                              4105-02 Service Charge Payable (Gusto)
 *                              4106-01 / -02 Accounts Payable - General &
 *                                Administrative — the account requested for
 *                                config('accounting.expenses.payable_account')
 *                                instead of reusing 4101-01 (AP - Operations).
 *
 *  2. The whole income-statement side of the workbook, which the previous
 *     seeder never touched at all (it only loaded the Statement of Financial
 *     Position): Shared G&A Expense, Sales, Cost of Sales, Fixed Expenses,
 *     Variable Expenses, Non-Operating Expenses, Other Income and Taxation.
 *
 * Notes on the source workbook (this section of it, same spirit as
 * ChartOfAccountsSeeder's notes on the balance sheet):
 *  - "SHARED G&A EXPENSE" 3101 sits inside the workbook's Assets total
 *    (between Non-Current Assets and Total Assets), exactly where "Shared
 *    Operating Cost" 3000-01/-02 already sits. It's the same kind of
 *    suspense/clearing account pending allocation between units, so it's
 *    typed OA (Other Assets) here too, NOT as a P&L expense.
 *  - The workbook's SALES group is numbered 8000, which collides with the
 *    Retained Earnings subgroup ChartOfAccountsSeeder already seeded at
 *    8000/8100-8400. Renumbered Sales to 8900-8922 to keep the leaves' last
 *    two digits recognisable while avoiding the clash. Cost of Sales (9000),
 *    Fixed Expenses (10000), Variable Expenses (11000), Non-Operating
 *    Expenses (12000), Other Income (13000) and Taxation (14000) were free
 *    and kept as-is.
 *  - Sales, Cost of Sales and Variable Expenses are Gusto-only (-02) in the
 *    workbook — no Maximilian leaves were given for these, so none are
 *    invented here. Add -01 leaves later if Maximilian needs them.
 *  - Taxation reused code 14101 for three different accounts (Annual Income
 *    Tax, Provision for Income Taxes, Prior Period Adjustment). Same fix as
 *    ChartOfAccountsSeeder: first one keeps 14101, the other two get the next
 *    free codes, seeded INACTIVE with a "PROVISIONAL CODE" description.
 *    Confirm with accounting, then activate.
 *  - Obvious typos in the source ("FrnB", "Gsuto", "usto", duplicate
 *    "TOTAL ..." rows) were cleaned up; nothing else was reworded.
 *
 * IMPORTANT ASSUMPTION: AccountType.category is assumed to accept 'revenue'
 * and 'expense' alongside the 'asset' / 'liability' / 'equity' values
 * ChartOfAccountsSeeder already uses. If your schema/enum only allows the
 * original three, adjust self::ACCOUNT_TYPES below before running.
 *
 * Safe to re-run: everything is upserted on (company_id, code).
 */
class IncomeStatementAccountsSeeder extends Seeder
{
    /** [code, name, category, normal_balance, description] */
    private const ACCOUNT_TYPES = [
        ['REV', 'Revenue', 'revenue', 'credit', 'Sales and related adjustments (discounts, returns, allowances).'],
        ['COGS', 'Cost of Sales', 'expense', 'debit', 'Direct cost of goods and inventory-related adjustments.'],
        ['OPEX', 'Operating Expenses', 'expense', 'debit', 'Fixed and variable expenses incurred running the business.'],
        ['NOE', 'Non-Operating Expenses', 'expense', 'debit', 'Bank charges, interest and other expenses outside normal operations.'],
        ['OI', 'Other Income', 'revenue', 'credit', 'Income outside normal sales activity.'],
        ['TAX', 'Income Tax Expense', 'expense', 'debit', 'Income tax provisions and related adjustments.'],
    ];

    public function run(): void
    {
        $company = CurrentCompany::find();

        DB::transaction(function () use ($company) {
            $types = $this->seedAccountTypes();

            Account::withoutEvents(function () use ($company, $types) {
                $this->seedMissingBalanceSheetLeaves($company, $types);
                $this->seedChildren($company, $this->incomeStatementChart(), null, $types);
            });
        });
    }

    /**
     * @return array<string, int> account type code => id (existing + new)
     */
    private function seedAccountTypes(): array
    {
        $ids = AccountType::pluck('id', 'code')->all();

        foreach (self::ACCOUNT_TYPES as [$code, $name, $category, $normalBalance, $description]) {
            $ids[$code] = AccountType::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'normal_balance' => $normalBalance,
                    'description' => $description,
                    'is_active' => true,
                ],
            )->id;
        }

        return $ids;
    }

    /**
     * Small additions under groups ChartOfAccountsSeeder already created.
     * Looked up by code rather than re-declared, since that seeder owns them.
     */
    private function seedMissingBalanceSheetLeaves(Company $company, array $types): void
    {
        $cash = Account::where('company_id', $company->id)->where('code', '1100')->first();
        $payableTrade = Account::where('company_id', $company->id)->where('code', '4100')->first();

        if (! $cash || ! $payableTrade) {
            throw new \RuntimeException(
                'IncomeStatementAccountsSeeder expects ChartOfAccountsSeeder to have already run '
                . '(groups 1100 and 4100 not found). Run ChartOfAccountsSeeder first.'
            );
        }

        $this->upsert($company, $cash, '1106-02', 'Cash in Bank - BPI (Account Number) (Gusto)', $types['CA']);
        $this->upsert($company, $cash, '1107-02', 'Check for Deposit (Gusto)', $types['CA']);

        $this->upsert($company, $payableTrade, '4104-01', 'Accounts Payable - Others (Maximilian)', $types['CL']);
        $this->upsert($company, $payableTrade, '4105-02', 'Service Charge Payable (Gusto)', $types['CL']);

        // The account requested for config('accounting.expenses.payable_account'),
        // kept separate from 4101-01 (AP - Operations).
        $this->upsert($company, $payableTrade, '4106-01', 'Accounts Payable - General & Administrative (Maximilian)', $types['CL']);
        $this->upsert($company, $payableTrade, '4106-02', 'Accounts Payable - General & Administrative (Gusto)', $types['CL']);
        $this->upsert($company, $payableTrade, '4107-01', 'A/P Clearing (Maxi)', $types['CL']);
        $this->upsert($company, $payableTrade, '4107-02', 'A/P Clearing (Gusto)', $types['CL']);
    }

    private function seedChildren(Company $company, array $node, ?Account $parent, array $types): void
    {
        foreach ($node['groups'] ?? [] as $group) {
            $account = $this->upsert($company, $parent, $group['code'], $group['name'], $types[$group['type']]);

            $this->seedChildren($company, $group, $account, $types);
        }

        foreach ($node['leaves'] ?? [] as $leaf) {
            [$code, $name] = $leaf;
            $options = $leaf[2] ?? [];

            $typeCode = $options['type'] ?? $node['type'];

            $this->upsert(
                $company,
                $parent,
                $code,
                $name,
                $types[$typeCode],
                $options['is_active'] ?? true,
                $options['description'] ?? null,
            );
        }
    }

    private function upsert(
        Company $company,
        ?Account $parent,
        string $code,
        string $name,
        int $accountTypeId,
        bool $isActive = true,
        ?string $description = null,
    ): Account {
        return Account::updateOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'account_type_id' => $accountTypeId,
                'parent_id' => $parent?->id,
                'name' => $name,
                'description' => $description,
                'is_control_account' => false,
                'is_active' => $isActive,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function incomeStatementChart(): array
    {
        return [
            'groups' => [
                [
                    'code' => '3100',
                    'name' => 'Shared General & Administrative Expense',
                    'type' => 'OA', // suspense/clearing account, same treatment as 3000-01/-02
                    'leaves' => [
                        ['3101-01', 'General & Administrative Expense (Maximilian)'],
                        ['3101-02', 'General & Administrative Expense (Gusto)'],
                        ['3102-01', 'Business Marketing Expense'],
                        ['3102-02', 'Business Marketing Expense'],
                        ['3103-01', 'Business Support Expense'],
                        ['3103-02', 'Business Support Expense'],
                        ['3104-01', 'Office Supplies Expense'],
                        ['3104-02', 'Office Supplies Expense'],
                        ['3105-01', 'Gen. & Admin. Transportation Expense'],
                        ['3105-02', 'Gen. & Admin. Transportation Expense'],
                        ['3106-01', 'Gen. & Admin. Maintenance Expense'],
                        ['3106-02', 'Gen. & Admin. Maintenance Expense'],
                        ['3107-01', 'Gen. & Admin. Utilities Expense'],
                        ['3107-02', 'Gen. & Admin. Utilities Expense'],
                        ['3108-01', 'Gen. & Admin. Security Expense'],
                        ['3108-02', 'Gen. & Admin. Security Expense'],
                        ['3109-01', 'Professional and Legal Fees'],
                        ['3109-02', 'Professional and Legal Fees'],
                        ['3110-01', 'Gen. & Admin. Miscellaneous Expense'],
                        ['3110-02', 'Gen. & Admin. Miscellaneous Expense'],
                    ],
                ],
                [
                    'code' => '8900',
                    'name' => 'Sales',
                    'type' => 'REV',
                    'leaves' => [
                        // Maximilian Sales accounts
                        ['8901-01', 'Revenue - Gross Rooms'],
                        ['8902-01', 'Venues'],
                        ['8903-01', 'Photoshoot'],
                        ['8904-01', 'Catering Services'],
                        ['8905-01', 'Senior Citizen Discounts'],
                        ['8906-01', 'PWD Discounts'],
                        ['8907-01', 'Employee Discounts'],
                        ['8908-01', 'Promo Discounts'],
                        ['8909-01', 'Special Open Discount'],
                        // Gusto Sales accounts
                        ['8901-02', 'Sales - Gross Food (FnB)'],
                        ['8902-02', 'Sales - Gross Food (KR)'],
                        ['8903-02', 'Sales - Gross Non-Food (FnB)'],
                        ['8904-02', 'Sales - Gross Non-Food (KR)'],
                        ['8905-02', 'Sales - Gross Beverage (FnB)'],
                        ['8906-02', 'Sales - Gross Beverage (KR)'],
                        ['8907-02', 'Sales Adjustment - Food (FnB)'],
                        ['8908-02', 'Sales Adjustment - Food (KR)'],
                        ['8909-02', 'Sales Adjustment - Non-Food (FnB)'],
                        ['8910-02', 'Sales Adjustment - Non-Food (KR)'],
                        ['8911-02', 'Sales Adjustment - Beverage (FnB)'],
                        ['8912-02', 'Sales Adjustment - Beverage (KR)'],
                        ['8913-02', 'Senior Citizen Discounts (FnB)'],
                        ['8914-02', 'Senior Citizen Discounts (KR)'],
                        ['8915-02', 'PWD Discounts (FnB)'],
                        ['8916-02', 'PWD Discounts (KR)'],
                        ['8917-02', 'Employee Discounts (FnB)'],
                        ['8918-02', 'Employee Discounts (KR)'],
                        ['8919-02', 'Promo Discounts (FnB)'],
                        ['8920-02', 'Promo Discounts (KR)'],
                        ['8921-02', 'Sales Return & Allowances (FnB)'],
                        ['8922-02', 'Sales Return & Allowances (KR)'],
                    ],
                ],
                [
                    'code' => '9000',
                    'name' => 'Cost of Sales',
                    'type' => 'COGS',
                    'leaves' => [
                        // Maximilian COGS detail accounts (from workbook)
                        ['9001-01', 'Room Departmental Costs'],
                        ['9002-01', 'Room Consumables'],
                        ['9003-01', 'Inventory Adjustments'],
                        // Existing Gusto COGS accounts
                        ['9101-02', 'Cost of Goods Sold - Food (FnB)'],
                        ['9102-02', 'Cost of Goods Sold - Food (KR)'],
                        ['9103-02', 'Cost of Goods Sold - Non-Food (FnB)'],
                        ['9104-02', 'Cost of Goods Sold - Non-Food (KR)'],
                        ['9105-02', 'Cost of Goods Sold - Beverage (Gusto)'],
                        ['9106-02', 'Inventory Adjustments - Food (Gusto)'],
                        ['9107-02', 'Inventory Adjustments - Non-Food (Gusto)'],
                        ['9108-02', 'Inventory Adjustments - Beverage (Gusto)'],
                        ['9109-02', 'Cost of Goods Sold Variance - Food (Gusto)'],
                        ['9110-02', 'Cost of Goods Sold Variance - Non-Food (Gusto)'],
                        ['9111-02', 'Cost of Goods Sold Variance - Beverage (Gusto)'],
                        ['9112-02', 'Purchase Discount - Food (Gusto)'],
                        ['9113-02', 'Purchase Discount - Non-Food (Gusto)'],
                        ['9114-02', 'Purchase Discount - Beverage (Gusto)'],
                        // Detailed Cost of Sales line items - separate leaf accounts (Gusto only per workbook)
                        ['9201-02', 'Meat'],
                        ['9202-02', 'Poultry'],
                        ['9203-02', 'Seafood'],
                        ['9204-02', 'Fruits'],
                        ['9205-02', 'Vegetables'],
                        ['9206-02', 'Condiments, Powders, & Seasonings'],
                        ['9207-02', 'Groceries'],
                        ['9208-02', 'Non-Alcoholic Beverages'],
                        ['9209-02', 'Alcoholic Beverages'],
                        ['9210-02', 'Functional Beverages'],
                    ],
                ],
                [
                    'code' => '10000',
                    'name' => 'Fixed Expenses',
                    'type' => 'OPEX',
                    'leaves' => [
                        ['10101-01', 'Depreciation Expense - Building (Maximilian)'],
                        ['10102-01', 'Depreciation Expense - Computer Equipment (Maximilian)'],
                        ['10103-01', 'Permit, Taxes and Licenses - SOC (Maximilian)'],
                        ['10103-02', 'Permit, Taxes and Licenses - SOC (Gusto)'],
                        ['10104-01', 'Depreciation Expense - Hotel Machinery & Equipment (Maximilian)'],
                        ['10104-02', 'Depreciation Expense - Store Machinery & Equipment (Gusto)'],
                        ['10105-01', 'Amortization Expense - Computer Software (Maximilian)'],
                        ['10105-02', 'Amortization Expense - Computer Software (Gusto)'],
                        ['10106-01', 'Depreciation Expense - Office Equipment (Maximilian)'],
                        ['10106-02', 'Depreciation Expense - Office Equipment (Gusto)'],
                        ['10107-01', 'Depreciation Expense - Office Furniture & Fixture (Maximilian)'],
                        ['10107-02', 'Depreciation Expense - Office Furniture & Fixture (Gusto)'],
                        ['10108-01', 'Depreciation Expense - Service Vehicle (Maximilian)'],
                        ['10108-02', 'Depreciation Expense - Service Vehicle (Gusto)'],
                        ['10109-01', 'Depreciation Expense - Hotel Furniture & Fixture (Maximilian)'],
                        ['10109-02', 'Depreciation Expense - Store Furniture & Fixture (Gusto)'],
                        // Maximilian-specific accounts
                        ['10201-01', 'Depreciation Expense - Hotel Furniture & Fixture (Maximilian)'],
                        ['10202-01', 'Depreciation Expense - Service Vehicle (Maximilian)'],
                        ['10203-01', 'Depreciation Expense - Office Furniture & Fixture (Maximilian)'],
                        ['10204-01', 'Depreciation Expense - Office Equipment (Maximilian)'],
                        ['10205-01', 'Amortization Expense - Computer Software (Maximilian)'],
                        ['10206-01', 'Gen. & Admin. Transportation Expense - GAEX (Maximilian)'],
                    ],
                ],
                [
                    'code' => '10500',
                    'name' => 'Selling Expenses',
                    'type' => 'OPEX',
                    'leaves' => [
                        ['10501-01', 'Store Salaries Expense'],
                        ['10502-01', 'Store SSS, PHIC, PAGIBIG'],
                        ['10503-01', 'Incentives and Commission'],
                        ['10504-01', 'Store Supplies Expense'],
                        ['10505-01', 'Store Transportation Expense'],
                        ['10506-01', 'Store Rent Expense'],
                        ['10507-01', 'Store Maintenance Expense'],
                        ['10508-01', 'Store Utilities Expense'],
                        ['10509-01', 'Store Miscellaneous Expense'],
                    ],
                ],
                [
                    'code' => '11000',
                    'name' => 'Variable Expenses',
                    'type' => 'OPEX',
                    'leaves' => [
                        // Maximilian side - Variable Expenses
                        ['11101-01', 'Sales Marketing Expense - OPEX (Maximilian)'],
                        ['11102-01', 'Front Office Salaries Expense - OPEX (Maximilian)'],
                        ['11103-01', 'Housekeeping Salaries Expense - OPEX (Maximilian)'],
                        ['11104-01', 'Linen Salaries Expense - OPEX (Maximilian)'],
                        ['11105-01', 'On-Call Salaries - OPEX (Maximilian)'],
                        ['11106-01', 'SSS Employer Share - OPEX (Maximilian)'],
                        ['11107-01', 'Pag-IBIG Employer Share - OPEX (Maximilian)'],
                        ['11108-01', 'PhilHealth Employer Share - OPEX (Maximilian)'],
                        ['11109-01', 'Incentives and Commission - OPEX (Maximilian)'],
                        ['11110-01', 'Hotel Supplies Expense - OPEX (Maximilian)'],
                        ['11111-01', 'Fuel and Oil Expense - OPEX (Maximilian)'],
                        ['11112-01', 'Transportation Expense - OPEX (Maximilian)'],
                        ['11113-01', 'Postage & Delivery Expense - OPEX (Maximilian)'],
                        ['11114-01', 'Repairs and Maintenance Expense - OPEX (Maximilian)'],
                        ['11115-01', 'Cleaning and Janitorial Expense - OPEX (Maximilian)'],
                        ['11116-01', 'Light and Water Expense - OPEX (Maximilian)'],
                        ['11117-01', 'Miscellaneous Expense - OPEX (Maximilian)'],
                        ['11118-01', 'Business Advertising Expense - GAEX (Maximilian)'],
                        ['11119-01', 'Gen. & Admin. Salaries Expense - GAEX (Maximilian)'],
                        ['11120-01', 'Gen. & Admin. SSS, PhilHealth, Pag-IBIG - GAEX (Maximilian)'],
                        ['11121-01', 'Office Supplies Expense - GAEX (Maximilian)'],
                        ['11122-01', 'Admin Transportation Expense - GAEX (Maximilian)'],
                        ['11123-01', 'Admin Maintenance Expense - GAEX (Maximilian)'],
                        ['11124-01', 'Admin Utilities Expense - GAEX (Maximilian)'],
                        ['11125-01', 'Admin Security Expense - GAEX (Maximilian)'],
                        ['11126-01', 'Insurance Expense - GAEX (Maximilian)'],
                        ['11127-01', 'Professional and Legal Fees - GAEX (Maximilian)'],
                        ['11128-01', 'Admin Miscellaneous Expense - GAEX (Maximilian)'],
                        // Gusto side - Variable Expenses (keeping existing)
                        ['11101-02', 'Sales & Marketing Expense - SOC (Gusto)'],
                        ['11102-02', 'Salaries Expense - SOC (Gusto)'],
                        ['11103-02', 'On-Call Salaries - SOC (Gusto)'],
                        ['11104-02', 'SSS Employer Share - SOC (Gusto)'],
                        ['11105-02', 'Pag-IBIG Employer Share - SOC (Gusto)'],
                        ['11106-02', 'PhilHealth Employer Share - SOC (Gusto)'],
                        ['11107-02', 'Incentives and Commission - SOC (Gusto)'],
                        ['11108-02', 'Store Supplies Expense - SOC (Gusto)'],
                        ['11109-02', 'Fuel and Oil Expense - SOC (Gusto)'],
                        ['11110-02', 'Transportation Expense - SOC (Gusto)'],
                        ['11111-02', 'Postage Expense - SOC (Gusto)'],
                        ['11112-02', 'Store Rent Expense - SOC (Gusto)'],
                        ['11113-02', 'Repairs and Maintenance Expense - SOC (Gusto)'],
                        ['11114-02', 'Housekeeping Expense - SOC (Gusto)'],
                        ['11115-02', 'Light and Water Expense - SOC (Gusto)'],
                        ['11116-02', 'Store Utilities Expense - SOC (Gusto)'],
                        ['11117-02', 'Miscellaneous Expense - SOC (Gusto)'],
                        ['11118-02', 'Business Advertising Expense - G&A (Gusto)'],
                        ['11119-02', 'Admin Salaries Expense - G&A (Gusto)'],
                        ['11120-02', 'Admin SSS, PhilHealth, Pag-IBIG - G&A (Gusto)'],
                        ['11121-02', 'Office Supplies Expense - G&A (Gusto)'],
                        ['11122-02', 'Admin Transportation Expense - G&A (Gusto)'],
                        ['11123-02', 'Admin Maintenance Expense - G&A (Gusto)'],
                        ['11124-02', 'Admin Utilities Expense - G&A (Gusto)'],
                        ['11125-02', 'Admin Security Expense - G&A (Gusto)'],
                        ['11126-02', 'Insurance Expense - G&A (Gusto)'],
                        ['11127-02', 'Professional and Legal Fees - G&A (Gusto)'],
                        ['11128-02', 'Admin Miscellaneous Expense - G&A (Gusto)'],
                    ],
                ],
                [
                    'code' => '12000',
                    'name' => 'Non-Operating Expenses',
                    'type' => 'NOE',
                    'leaves' => [
                        ['12101-01', 'Bank Charges - Credit Card (Maximilian)'],
                        ['12101-02', 'Bank Charges - Credit Card (Gusto)'],
                        ['12102-01', 'Interest Expense - G&A (Maximilian)'],
                        ['12102-02', 'Interest Expense - G&A (Gusto)'],
                        ['12103-01', 'Other Expenses (Maximilian)'],
                        ['12103-02', 'Other Expenses (Gusto)'],
                        ['12104-01', 'Representation Expense (Maximilian)'],
                        ['12104-02', 'Representation Expense (Gusto)'],
                    ],
                ],
                [
                    'code' => '13000',
                    'name' => 'Other Income',
                    'type' => 'OI',
                    'leaves' => [
                        ['13101-01', 'Cash Overage - Front Desk Remittance (Maximilian)'],
                        ['13101-02', 'Cash Overage - Cashier Remittance (Gusto)'],
                        ['13102-01', 'Other Income (Maximilian)'],
                        ['13102-02', 'Other Income (Gusto)'],
                        ['13103-02', 'Income from Used Oil (Gusto)'],
                        ['13104-01', 'Lates and Penalties (Maximilian)'],
                        ['13104-02', 'Lates and Penalties (Gusto)'],
                        ['13105-01', 'Miscellaneous Fee (Maximilian)'],
                        ['13105-02', 'Miscellaneous Fee (Gusto)'],
                        ['13106-01', 'Interest Income from Bank Deposits (Maximilian)'],
                        ['13106-02', 'Interest Income from Bank Deposits (Gusto)'],
                    ],
                ],
                [
                    'code' => '14000',
                    'name' => 'Taxation',
                    'type' => 'TAX',
                    'leaves' => [
                        ['14101-01', 'Annual Income Tax (Maximilian)'],
                        ['14101-02', 'Annual Income Tax (Gusto)'],
                        ['14102-01', 'Provision for Income Taxes (Maximilian)', ['is_active' => false, 'description' => 'PROVISIONAL CODE. The workbook listed this account as 14101-01, which is already used by another account. Confirm the final code, then activate.']],
                        ['14102-02', 'Provision for Income Taxes (Gusto)', ['is_active' => false, 'description' => 'PROVISIONAL CODE. The workbook listed this account as 14101-02, which is already used by another account. Confirm the final code, then activate.']],
                        ['14103-01', 'Prior Period Adjustment (Maximilian)', ['is_active' => false, 'description' => 'PROVISIONAL CODE. The workbook listed this account as 14101-01, which is already used by another account. Confirm the final code, then activate.']],
                        ['14103-02', 'Prior Period Adjustment (Gusto)', ['is_active' => false, 'description' => 'PROVISIONAL CODE. The workbook listed this account as 14101-02, which is already used by another account. Confirm the final code, then activate.']],
                    ],
                ],
            ],
        ];
    }
}
