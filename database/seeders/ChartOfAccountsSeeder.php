<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the chart of accounts from "Maxi Working Paper 2026" (Statement of
 * Financial Position, September 2026) into the current company.
 *
 * The workbook has one sheet per business unit. There is a single company;
 * the code suffix only tells the accountant which unit an account belongs to:
 *
 *   -01 = Maximilian      -02 = Gusto
 *
 * Both sheets share the same group accounts (1000, 1100, ...), so each group
 * is created once with the -01 and -02 accounts side by side under it.
 * Safe to re-run: everything is upserted on (company_id, code).
 *
 * Notes on the source workbook:
 *  - Exact repeated rows (1504-01, 1609-01) were seeded once.
 *  - Codes reused by different accounts (Maxi 4201-01, 5103-01; Gusto
 *    1313-02) keep the first account on the original code. The others got the
 *    next free code in that range, are seeded INACTIVE, and carry a
 *    "PROVISIONAL CODE" description. Confirm them with accounting, fix the
 *    code, then activate.
 *  - Names carry a normalised "(Maximilian)" / "(Gusto)" tag matching the code
 *    suffix, so pickers stay unambiguous. 5000 and 8000 are nested under
 *    4000 and 7000 to match the workbook's subtotals.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /** [code, name, category, normal_balance, description] */
    private const ACCOUNT_TYPES = [
        ['CA', 'Current Assets', 'asset', 'debit', 'Cash, receivables, inventory and other assets realised within a year.'],
        ['NCA', 'Non-Current Assets', 'asset', 'debit', 'Fixed assets at cost.'],
        ['ACCDEP', 'Accumulated Depreciation', 'asset', 'credit', 'Contra-asset: reduces the related fixed asset.'],
        ['OA', 'Other Assets', 'asset', 'debit', 'Assets outside the current / non-current groups (Shared Operating Cost).'],
        ['CL', 'Current Liabilities', 'liability', 'credit', 'Payables, statutory remittances and accruals due within a year.'],
        ['NCL', 'Non-Current Liabilities', 'liability', 'credit', 'Long-term loans and other obligations.'],
        ['EQ', 'Capital and Reserves', 'equity', 'credit', 'Capital stock and paid-up capital.'],
        ['RE', 'Retained Earnings', 'equity', 'credit', 'Net income, retained earnings and income summary.'],
    ];

    public function run(): void
    {
        // Single-tenant: everything goes into the app's current company.
        $company = CurrentCompany::find();

        DB::transaction(function () use ($company) {
            $types = $this->seedAccountTypes();

            // Skip model events so the audit trail isn't filled with (or
            // tripped up by) seeding rows that have no acting user.
            Account::withoutEvents(function () use ($company, $types) {
                $this->seedChildren($company, $this->chart(), null, null, $types);
            });
        });
    }

    /**
     * @return array<string, int> account type code => id
     */
    private function seedAccountTypes(): array
    {
        $ids = [];

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
     * Seeds the sub-groups and leaf accounts of $node under $parent.
     * Leaves inherit the type of the group they sit in, except accumulated
     * depreciation (credit-normal contra assets) and explicit overrides.
     */
    private function seedChildren(Company $company, array $node, ?Account $parent, ?string $inheritedType, array $types): void
    {
        foreach ($node['groups'] ?? [] as $group) {
            $account = $this->upsert($company, $parent, $group['code'], $group['name'], $types[$group['type']]);

            $this->seedChildren($company, $group, $account, $group['type'], $types);
        }

        foreach ($node['leaves'] ?? [] as $leaf) {
            [$code, $name] = $leaf;
            $options = $leaf[2] ?? [];

            $typeCode = $options['type']
                ?? (str_contains($name, 'Acc Depn') ? 'ACCDEP' : $inheritedType);

            // Trade receivables (13xx) and Accounts Payable - Operations are
            // backed by a subledger. Adjust if your control accounts differ.
            $isControl = $parent?->code === '1300' || str_starts_with($code, '4101-');

            $this->upsert(
                $company,
                $parent,
                $code,
                $name,
                $types[$typeCode],
                $isControl,
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
        bool $isControl = false,
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
                'is_control_account' => $isControl,
                'is_active' => $isActive,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function chart(): array
    {
        return [
            'groups' => [
                [
                    'code' => '1000',
                    'name' => 'Current Assets',
                    'type' => 'CA',
                    'groups' => [
                        [
                            'code' => '1100',
                            'name' => 'Cash',
                            'type' => 'CA',
                            'leaves' => [
                                ['1101-01', 'Cash on hand (Maximilian)'],
                                ['1101-02', 'Cash on hand (Gusto)'],
                                ['1102-01', 'Cash in Bank - BDO (Account Number) (Maximilian)'],
                                ['1102-02', 'Cash in Bank - BDO (Account Number) (Gusto)'],
                                ['1103-01', 'Cash in Bank - CBC (118400001538) (Maximilian)'],
                                ['1103-02', 'Cash in Bank - CBC (118400006556) (Gusto)'],
                                ['1104-01', 'Cash in Bank - CBC (______) (Maximilian)'],
                                ['1104-02', 'Cash in Bank - BPI (Account Number) (Gusto)'],
                                ['1105-01', 'Cash in Bank - CBC (______) (Maximilian)'],
                                ['1105-02', 'Check For Deposit (Gusto)'],
                                ['1106-01', 'Cash in Bank - BPI (Account Number) (Maximilian)'],
                                ['1107-01', 'Check For Deposit (Maximilian)'],
                            ],
                        ],
                        [
                            'code' => '1200',
                            'name' => 'Other Cash',
                            'type' => 'CA',
                            'leaves' => [
                                ['1201-01', 'Change Fund (Maximilian)'],
                                ['1201-02', 'Change Fund (Gusto)'],
                                ['1202-01', 'Petty Cash Fund (Maximilian)'],
                                ['1203-01', 'Revolving Fund (Maximilian)'],
                            ],
                        ],
                        [
                            'code' => '1300',
                            'name' => 'Receivable Trades/Services',
                            'type' => 'CA',
                            'leaves' => [
                                ['1301-01', 'Accounts Receivable - Hotel Accommodation - Rooms (Maximilian)'],
                                ['1302-01', 'Accounts Receivable - Venue - Grand Ballroom (Maximilian)'],
                                ['1303-01', 'Accounts Receivable - Venue - Ballroom A (Maximilian)'],
                                ['1304-01', 'Accounts Receivable - Venue - Ballroom B (Maximilian)'],
                                ['1305-01', 'Accounts Receivable - Venue - Kamagong (Maximilian)'],
                                ['1306-01', 'Accounts Receivable - Venue - Belvedere (Maximilian)'],
                                ['1307-01', 'Accounts Receivable - Venue - Tangile (Maximilian)'],
                                ['1308-01', 'Accounts Receivable - Venue - Tindalo (Maximilian)'],
                                ['1309-01', 'Accounts Receivable - Shuttle Services (Maximilian)'],
                                ['1310-01', 'Accounts Receivable - Meals (Maximilian)'],
                                ['1310-02', 'Accounts Receivable - Meals (Gusto)'],
                                ['1311-01', 'Accounts Receivable - Gift Certificate/Voucher (Maximilian)'],
                                ['1311-02', 'Accounts Receivable - Gift Certificate/Voucher (Gusto)'],
                                ['1312-01', 'Accounts Receivable - Others (Maximilian)'],
                                ['1312-02', 'Accounts Receivable - Others (Gusto)'],
                                ['1313-02', 'Accounts Receivable Trade - STF KR (Gusto)'],
                                ['1314-01', 'AR Affiliate - STF Gusto (Maximilian)'],
                                ['1314-02', 'AR Affiliate - STF Maximilian (Gusto)'],
                                ['1315-02', 'Accounts Receivable Trade - STF Gusto (KR) (Gusto)', ['is_active' => false, 'description' => 'PROVISIONAL CODE. The workbook listed this account as 1313-02, which is already used by another account. Confirm the final code, then activate.']],
                            ],
                        ],
                        [
                            'code' => '1400',
                            'name' => 'Receivable Non-Trades',
                            'type' => 'CA',
                            'leaves' => [
                                ['1401-01', 'Cash Advances for Liquidation (Maximilian)'],
                                ['1401-02', 'Cash Advances for Liquidation (Gusto)'],
                                ['1402-01', 'AR Employees (Maximilian)'],
                                ['1402-02', 'AR Employees (Gusto)'],
                                ['1403-01', 'AR Others (Maximilian)'],
                                ['1403-02', 'AR Others (Gusto)'],
                                ['1404-01', 'Due From Affiliates - Gusto (Maximilian)'],
                                ['1404-02', 'Due From Affiliates - Maximilian (Gusto)'],
                                ['1405-01', 'Due From Old Accounts (Forwarded Balance) (Maximilian)'],
                                ['1405-02', 'Due From Old Accounts (Forwarded Balance) (Gusto)'],
                                ['1406-01', 'AR Clearing Account (Maximilian)'],
                                ['1406-02', 'AR Clearing Account (Gusto)'],
                                ['1407-01', 'Cash Advances - Purchases (Maximilian)'],
                                ['1407-02', 'Cash Advances - Purchases (Gusto)'],
                            ],
                        ],
                        [
                            'code' => '1500',
                            'name' => 'Inventory',
                            'type' => 'CA',
                            'leaves' => [
                                ['1501-01', 'Merchandise Inventory General Storage (Maximilian)'],
                                ['1501-02', 'Merchandise Inventory General Storage (Gusto)'],
                                ['1502-01', 'Merchandise Inventory Hotel (Maximilian)'],
                                ['1502-02', 'Merchandise Inventory Fnb (Gusto)'],
                                ['1503-01', 'Merchandise Inventory Bad Stocks (Maximilian)'],
                                ['1503-02', 'Merchandise Inventory Bad Stocks (Gusto)'],
                                ['1504-01', 'Inventory - Uniform (Maximilian)'],
                                ['1505-01', 'Merchandise Inventory Variance (Maximilian)'],
                                ['1505-02', 'Merchandise Inventory Variance (Gusto)'],
                                ['1506-01', 'Merchandise Inventory - Breakage (Maximilian)'],
                                ['1506-02', 'Merchandise Inventory - Breakage (Gusto)'],
                            ],
                        ],
                        [
                            'code' => '1600',
                            'name' => 'Other Current Assets',
                            'type' => 'CA',
                            'leaves' => [
                                ['1601-01', 'Prepaid Insurance (Maximilian)'],
                                ['1601-02', 'Prepaid Insurance (Gusto)'],
                                ['1602-01', 'Prepaid Office Supplies (Maximilian)'],
                                ['1602-02', 'Prepaid Office Supplies (Gusto)'],
                                ['1603-01', 'Prepaid Repair and Maintenance (Maximilian)'],
                                ['1603-02', 'Prepaid Repair and Maintenance (Gusto)'],
                                ['1604-01', 'Prepaid Hotel Supplies (Maximilian)'],
                                ['1604-02', 'Prepaid Resto/Cafe Supplies (Gusto)'],
                                ['1605-01', 'Prepaid Taxes and Licenses (Maximilian)'],
                                ['1605-02', 'Prepaid Taxes and Licenses (Gusto)'],
                                ['1606-01', 'Prepaid Expenses - Others (Maximilian)'],
                                ['1606-02', 'Prepaid Expenses - Others (Gusto)'],
                                ['1607-01', 'Creditable Withholding Tax - Hotel (Maximilian)'],
                                ['1607-02', 'Creditable Withholding Tax - Resto/Cafe (Gusto)'],
                                ['1608-01', 'Input Tax (Maximilian)'],
                                ['1608-02', 'Input Tax (Gusto)'],
                                ['1609-01', 'Input Tax - Non Trade/Services (Maximilian)'],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => '2000',
                    'name' => 'Non-Current Assets',
                    'type' => 'NCA',
                    'groups' => [
                        [
                            'code' => '2100',
                            'name' => 'Fixed Assets',
                            'type' => 'NCA',
                            'leaves' => [
                                ['2101-01', 'Building (Maximilian)'],
                                ['2102-01', 'Computer Equipment (Maximilian)'],
                                ['2102-02', 'Computer Equipment (Gusto)'],
                                ['2103-01', 'Computer Equipment - Acc Depn (Maximilian)'],
                                ['2103-02', 'Computer Equipment - Acc Depn (Gusto)'],
                                ['2104-01', 'Computer Software (Maximilian)'],
                                ['2104-02', 'Computer Software (Gusto)'],
                                ['2105-01', 'Computer Software - Acc Depn (Maximilian)'],
                                ['2105-02', 'Computer Software - Acc Depn (Gusto)'],
                                ['2106-01', 'Machinery and Equipment (Maximilian)'],
                                ['2106-02', 'Machinery and Equipment (Gusto)'],
                                ['2107-01', 'Machinery and Equipment - Acc Depn (Maximilian)'],
                                ['2107-02', 'Machinery and Equipment - Acc Depn (Gusto)'],
                                ['2108-01', 'Office Equipment (Maximilian)'],
                                ['2108-02', 'Office Equipment (Gusto)'],
                                ['2109-01', 'Office Equipment - Acc Depn (Maximilian)'],
                                ['2109-02', 'Office Equipment - Acc Depn (Gusto)'],
                                ['2110-01', 'Office Furniture & Fixtures (Maximilian)'],
                                ['2110-02', 'Office Furniture & Fixtures (Gusto)'],
                                ['2111-01', 'Office Furniture & Fixtures - Acc Depn (Maximilian)'],
                                ['2111-02', 'Office Furniture & Fixtures - Acc Depn (Gusto)'],
                                ['2112-01', 'Service Vehicle (Maximilian)'],
                                ['2113-01', 'Service Vehicle - Acc Depn (Maximilian)'],
                                ['2114-01', 'Hotel Equipment (Maximilian)'],
                                ['2114-02', 'Resto/Café Equipment (Gusto)'],
                                ['2115-01', 'Hotel Equipment - Acc Depn (Maximilian)'],
                                ['2115-02', 'Resto/Cafe Equipment - Acc Depn (Gusto)'],
                                ['2116-01', 'Hotel Furniture & Fixtures (Maximilian)'],
                                ['2116-02', 'Resto/Cafe Furniture & Fixtures (Gusto)'],
                                ['2117-01', 'Hotel Furniture & Fixtures - Acc Depn (Maximilian)'],
                                ['2117-02', 'Resto/Cafe Furniture & Fixtures - Acc Depn (Gusto)'],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => '4000',
                    'name' => 'Current Liabilities',
                    'type' => 'CL',
                    'groups' => [
                        [
                            'code' => '4100',
                            'name' => 'Payable Trade/Services',
                            'type' => 'CL',
                            'leaves' => [
                                ['4101-01', 'Accounts Payable - Operations (Maximilian)'],
                                ['4101-02', 'Accounts Payable - Operations (Gusto)'],
                                ['4102-01', 'Output Tax (Maximilian)'],
                                ['4102-02', 'Output Tax (Gusto)'],
                                ['4103-01', 'AP Affiliate - STF Gusto (Maximilian)'],
                                ['4103-02', 'AP Affiliate - STF Maximilian (Gusto)'],
                            ],
                        ],
                        [
                            'code' => '4200',
                            'name' => 'Payable Non-Trade/Services',
                            'type' => 'CL',
                            'leaves' => [
                                ['4201-01', 'Due To Affiliates - Gusto (Maximilian)'],
                                ['4201-02', 'Due To Affiliates - Maximilian (Gusto)'],
                                ['4202-01', 'HDMF Loans Payable (Maximilian)'],
                                ['4202-02', 'HDMF Loans Payable (Gusto)'],
                                ['4203-01', 'HDMF Payable (Maximilian)'],
                                ['4203-02', 'HDMF Payable (Gusto)'],
                                ['4204-01', 'Philhealth Payable (Maximilian)'],
                                ['4204-02', 'Philhealth Payable (Gusto)'],
                                ['4205-01', 'SSS Payable (Maximilian)'],
                                ['4205-02', 'SSS Payable (Gusto)'],
                                ['4206-01', 'SSS Loans Payable (Maximilian)'],
                                ['4206-02', 'SSS Loans Payable (Gusto)'],
                                ['4207-01', 'Withholding Tax Payable - Expanded (Services) (Maximilian)'],
                                ['4207-02', 'Withholding Tax Payable - Expanded (Goods) (Gusto)'],
                                ['4208-02', 'Withholding Tax Payable - Non-Trade (Goods) (Gusto)'],
                                ['4209-01', 'VAT Payable (Maximilian)'],
                                ['4209-02', 'VAT Payable (Gusto)'],
                                ['4210-01', 'AP-Employees (Maximilian)'],
                                ['4210-02', 'AP-Employees (Gusto)'],
                                ['4211-01', 'Collection Payable (Maximilian)'],
                                ['4211-02', 'Collection Payable (Gusto)'],
                                ['4212-01', 'AP Others (Maximilian)'],
                                ['4212-02', 'AP Others (Gusto)'],
                                ['4213-01', 'Due To Affiliates - Maximilian (Maximilian)', ['is_active' => false, 'description' => 'PROVISIONAL CODE. The workbook listed this account as 4201-01, which is already used by another account. Confirm the final code, then activate.']],
                            ],
                        ],
                        [
                            'code' => '5000',
                            'name' => 'Accrued Expenses',
                            'type' => 'CL',
                            'leaves' => [
                                ['5100-01', 'Accrued 13th Month Pay Expense (Maximilian)'],
                                ['5100-02', 'Accrued 13th Month Pay Expense (Gusto)'],
                                ['5101-01', 'Accrued Expenses (Maximilian)'],
                                ['5101-02', 'Accrued Expenses (Gusto)'],
                                ['5102-01', 'Accrued Fuel Expense (Maximilian)'],
                                ['5103-01', 'Accrued Utility Expense - Electricity (Maximilian)'],
                                ['5104-01', 'Accrued Professional Fee (Maximilian)'],
                                ['5105-01', 'Accrued Utility Expense - Water (Maximilian)', ['is_active' => false, 'description' => 'PROVISIONAL CODE. The workbook listed this account as 5103-01, which is already used by another account. Confirm the final code, then activate.']],
                                ['5106-01', 'Accrued Utility Expense - GenSet (Maximilian)', ['is_active' => false, 'description' => 'PROVISIONAL CODE. The workbook listed this account as 5103-01, which is already used by another account. Confirm the final code, then activate.']],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => '6000',
                    'name' => 'Non-Current Liabilities',
                    'type' => 'NCL',
                    'leaves' => [
                        ['6100-01', 'Other Loans Payable (Maximilian)'],
                        ['6100-02', 'Other Loans Payable (Gusto)'],
                    ],
                ],
                [
                    'code' => '7000',
                    'name' => 'Capital and Reserves',
                    'type' => 'EQ',
                    'groups' => [
                        [
                            'code' => '7100',
                            'name' => 'Capital Stocks',
                            'type' => 'EQ',
                            'leaves' => [
                                ['7101-01', 'Capital Stock (Maximilian)'],
                                ['7101-02', 'Capital Stock (Gusto)'],
                                ['7102-01', 'Subscribed Capital Stock (Maximilian)'],
                                ['7102-02', 'Subscribed Capital Stock (Gusto)'],
                                ['7103-01', 'Paid Up Capital (Maximilian)'],
                                ['7103-02', 'Paid Up Capital (Gusto)'],
                            ],
                        ],
                        [
                            'code' => '8000',
                            'name' => 'Retained Earnings',
                            'type' => 'RE',
                            'leaves' => [
                                ['8100-01', 'Net Income (Maximilian)'],
                                ['8100-02', 'Net Income (Gusto)'],
                                ['8200-01', 'Unappropriated Retained Earnings (Maximilian)'],
                                ['8200-02', 'Unappropriated Retained Earnings (Gusto)'],
                                ['8300-01', 'Appropriated Retained Earnings (Maximilian)'],
                                ['8300-02', 'Appropriated Retained Earnings (Gusto)'],
                                ['8400-01', 'Income Summary (Maximilian)'],
                                ['8400-02', 'Income Summary (Gusto)'],
                            ],
                        ],
                    ],
                ],
            ],
            'leaves' => [
                ['3000-01', 'Shared Operating Cost (Maximilian)', ['type' => 'OA']],
                ['3000-02', 'Shared Operating Cost (Gusto)', ['type' => 'OA']],
            ],
        ];
    }
}