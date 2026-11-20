<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountType;
use App\Support\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles the previously seeded chart with
 * "001_Final Draft_Chart of Accounts.xlsx".
 *
 * Run this instead of running the two legacy chart seeders individually:
 *
 *   php artisan db:seed --class=FinalChartOfAccountsSeeder
 *
 * The existing seeders are intentionally reused as the starting point so
 * account IDs and posted journal-entry references are preserved. This
 * seeder then applies the final workbook's account-code layout.
 *
 * Duplicate codes in the workbook are assigned unique provisional codes.
 * Those rows are inactive until accounting confirms the assigned codes.
 */
class FinalChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Preserve existing account IDs and the current chart's seeded types.
            app(ChartOfAccountsSeeder::class)->run();
            app(IncomeStatementAccountsSeeder::class)->run();

            $company = CurrentCompany::find();

            Account::withoutEvents(function () use ($company) {
                $this->reconcileBalanceSheetCodes($company);
                $this->reconcileIncomeStatementRanges($company);
                $this->reconcileTradePayables($company);
                $this->seedUnearnedRevenue($company);
                $this->restoreSharedExpenseGroupName($company);
            });
        });
    }

    /**
     * The final workbook moves the old accrued-expense group (5000) to 4300,
     * moves non-current liabilities/capital/retained earnings up by one
     * thousand, and reuses 4301 for several accounts. Temporary codes avoid
     * unique(company_id, code) collisions while the existing rows are moved.
     */
    private function reconcileBalanceSheetCodes($company): void
    {
        $moves = [
            // Accrued expenses: source workbook repeats 4301-01 and 4301-02.
            '5100-01' => ['4301-01', true, null],
            '5101-01' => ['4302-01', false, 'PROVISIONAL CODE. The final workbook repeats 4301-01. Assigned 4302-01 pending accountant confirmation.'],
            '5102-01' => ['4303-01', false, 'PROVISIONAL CODE. The final workbook repeats 4301-01. Assigned 4303-01 pending accountant confirmation.'],
            '5103-01' => ['4304-01', false, 'PROVISIONAL CODE. The final workbook repeats 4301-01. Assigned 4304-01 pending accountant confirmation.'],
            '5105-01' => ['4305-01', false, 'PROVISIONAL CODE. The final workbook repeats 4301-01. Assigned 4305-01 pending accountant confirmation.'],
            '5106-01' => ['4306-01', false, 'PROVISIONAL CODE. The final workbook repeats 4301-01. Assigned 4306-01 pending accountant confirmation.'],
            '5104-01' => ['4307-01', false, 'PROVISIONAL CODE. The final workbook repeats 4301-01. Assigned 4307-01 pending accountant confirmation.'],
            '5101-02' => ['4301-02', true, null],
            '5100-02' => ['4302-02', false, 'PROVISIONAL CODE. The final workbook repeats 4301-02. Assigned 4302-02 pending accountant confirmation.'],

            // New workbook numbering for accrued expenses and the remaining balance-sheet sections.
            '5000' => ['4300', true, null],
            '6000' => ['5000', true, null],
            '6100-02' => ['5101-02', true, null],
            '7000' => ['6000', true, null],
            '7100' => ['6100', true, null],
            '7101-01' => ['6101-01', true, null],
            '7101-02' => ['6101-02', true, null],
            '7102-01' => ['6102-01', true, null],
            '7102-02' => ['6102-02', true, null],
            '7103-01' => ['6103-01', true, null],
            '7103-02' => ['6103-02', true, null],
            '8000' => ['7000', true, null],
            '8100-01' => ['7100-01', true, null],
            '8100-02' => ['7100-02', true, null],
            '8200-01' => ['7200-01', true, null],
            '8200-02' => ['7200-02', true, null],
            '8300-01' => ['7300-01', true, null],
            '8300-02' => ['7300-02', true, null],
            '8400-01' => ['7400-01', true, null],
            '8400-02' => ['7400-02', true, null],
        ];

        $accounts = Account::where('company_id', $company->id)
            ->whereIn('code', array_keys($moves))
            ->get()
            ->keyBy('code');

        // First move all affected records out of the destination namespace.
        foreach ($accounts as $account) {
            $account->forceFill(['code' => '__final_coa_' . $account->id])->save();
        }

        foreach ($moves as $oldCode => [$newCode, $active, $description]) {
            $account = $accounts->get($oldCode);

            if (! $account) {
                continue;
            }

            $account->forceFill([
                'code' => $newCode,
                'is_active' => $active,
                'description' => $description,
            ])->save();
        }

        Account::where('company_id', $company->id)->where('code', '6100-01')->update([
            'is_active' => false,
            'description' => 'LEGACY ACCOUNT. The final workbook lists Other Loans Payable only for Gusto.',
        ]);

        $this->renameAccount($company, '5000', 'Non-Current Liabilities');
        $this->ensureLoansPayableGroup($company);
        $this->renameAccount($company, '5100', 'Loans Payable');
        $this->renameAccount($company, '6000', 'Capital and Reserves');
        $this->renameAccount($company, '6100', 'Capital Stocks');
        $this->renameAccount($company, '7000', 'Retained Earnings');

        // The old 5100 group is now the workbook's 4300 accrued-expense group.
        $this->renameAccount($company, '4300', 'Accrued Expenses');
    }

    /**
     * The old income-statement seeder used 9000/10000/11000/12000/13000/14000
     * for COGS/fixed/variable/non-operating/other-income/taxation. The final
     * workbook uses 10000/11000/12000/13000/14000/15000 respectively.
     */
    private function reconcileIncomeStatementRanges($company): void
    {
        $typePrefixes = [
            'COGS' => ['9000', '90', '91', '92'],
            'OPEX' => ['10000', '10', '11'],
            'NOE' => ['12000', '12'],
            'OI' => ['13000', '13'],
            'TAX' => ['14000', '14'],
        ];

        $moves = [];

        foreach ($typePrefixes as $typeCode => $prefixes) {
            $type = AccountType::where('code', $typeCode)->first();

            if (! $type) {
                continue;
            }

            $rows = Account::where('company_id', $company->id)
                ->where('account_type_id', $type->id)
                ->get()
                ->filter(fn (Account $account) => collect($prefixes)->contains(
                    fn ($prefix) => str_starts_with($account->code, $prefix)
                ));

            foreach ($rows as $account) {
                $code = $account->code;
                $newCode = $code;

                if ($typeCode === 'COGS') {
                    if ($code === '9000') {
                        $newCode = '10000';
                    } elseif (preg_match('/^(90|91|92)(\\d+)(-\\d{2})?$/', $code, $m)) {
                        $newCode = (string) ((int) $m[1] * 100 + (int) $m[2] + 1000) . ($m[3] ?? '');
                    }
                } elseif ($typeCode === 'OPEX') {
                    if ($code === '10000') {
                        $newCode = '11000';
                    } elseif ($code === '11000') {
                        $newCode = '12000';
                    } elseif (preg_match('/^(10|11)(\\d{3})(-\\d{2})?$/', $code, $m)) {
                        $newCode = (string) ((int) $m[1] * 1000 + (int) $m[2] + 1000) . ($m[3] ?? '');
                    }
                } elseif ($typeCode === 'NOE') {
                    if ($code === '12000') {
                        $newCode = '13000';
                    } elseif (preg_match('/^12(\\d{3})(-\\d{2})?$/', $code, $m)) {
                        $newCode = '13' . $m[1] . ($m[2] ?? '');
                    }
                } elseif ($typeCode === 'OI') {
                    if ($code === '13000') {
                        $newCode = '14000';
                    } elseif (preg_match('/^13(\\d{3})(-\\d{2})?$/', $code, $m)) {
                        $newCode = '14' . $m[1] . ($m[2] ?? '');
                    }
                } elseif ($typeCode === 'TAX') {
                    if ($code === '14000') {
                        $newCode = '15000';
                    } elseif (preg_match('/^14(\\d{3})(-\\d{2})?$/', $code, $m)) {
                        $newCode = '15' . $m[1] . ($m[2] ?? '');
                    }
                }

                if ($newCode !== $code) {
                    $moves[$account->id] = [$account, $newCode];
                }
            }
        }

        // Move every source row aside first so destinations can overlap
        // existing legacy codes without violating the company/code unique key.
        foreach ($moves as $id => [$account]) {
            $account->forceFill(['code' => '__final_pnl_' . $id])->save();
        }

        foreach ($moves as [$account, $newCode]) {
            $account->forceFill(['code' => $newCode])->save();
        }

        // This standalone group was not present in the final workbook; the
        // final chart groups selling costs under Variable Expenses instead.
        Account::where('company_id', $company->id)
            ->where(function ($query) {
                $query->where('code', '10500')
                    ->orWhere('code', 'like', '105%');
            })
            ->update([
                'is_active' => false,
                'description' => 'LEGACY ACCOUNT. Not listed as a separate group in the final accountant-approved workbook.',
            ]);
    }

    private function ensureLoansPayableGroup($company): void
    {
        $parent = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $type = AccountType::where('code', 'NCL')->firstOrFail();

        $group = Account::updateOrCreate(
            ['company_id' => $company->id, 'code' => '5100'],
            [
                'account_type_id' => $type->id,
                'parent_id' => $parent?->id,
                'name' => 'Loans Payable',
                'description' => null,
                'is_control_account' => false,
                'is_active' => true,
            ],
        );

        Account::where('company_id', $company->id)
            ->where('code', '5101-02')
            ->update(['parent_id' => $group->id]);
    }

    private function reconcileTradePayables($company): void
    {
        $currentLiabilities = Account::where('company_id', $company->id)
            ->where('code', '4100')
            ->first();

        if (! $currentLiabilities) {
            throw new \RuntimeException('Final CoA reconciliation could not find account group 4100.');
        }

        $this->upsert($company, $currentLiabilities, '4105-01', 'Accounts Payable - AVL Service', 'CL');

        $massage = Account::where('company_id', $company->id)->where('code', '4106-01')->first();
        if ($massage) {
            $massage->forceFill([
                'name' => 'Accounts Payable - Massage Service',
                'is_active' => true,
                'description' => null,
            ])->save();
        }

        $suppliers = Account::where('company_id', $company->id)->where('code', '4107-01')->first();
        if ($suppliers) {
            $suppliers->forceFill([
                'name' => 'Accounts Payable - Other Suppliers',
                'is_active' => true,
                'description' => null,
            ])->save();
        } else {
            $this->upsert($company, $currentLiabilities, '4107-01', 'Accounts Payable - Other Suppliers', 'CL');
        }

        // These were introduced by the previous working-paper seeder but do
        // not appear in the final workbook's payable section.
        Account::where('company_id', $company->id)
            ->whereIn('code', ['4106-02', '4107-02'])
            ->update([
                'is_active' => false,
                'description' => 'LEGACY ACCOUNT. Not listed in the final accountant-approved workbook.',
            ]);
    }

    private function seedUnearnedRevenue($company): void
    {
        $currentLiabilities = Account::where('company_id', $company->id)
            ->where('code', '4000')
            ->first();

        $type = AccountType::where('code', 'CL')->firstOrFail();

        $group = Account::updateOrCreate(
            ['company_id' => $company->id, 'code' => '4400'],
            [
                'account_type_id' => $type->id,
                'parent_id' => $currentLiabilities?->id,
                'name' => 'Unearned Revenue',
                'description' => null,
                'is_control_account' => false,
                'is_active' => true,
            ],
        );

        $this->upsert($company, $group, '4401-01', 'Unearned Revenue - Gift Voucher', 'CL');
    }

    private function restoreSharedExpenseGroupName($company): void
    {
        $type = AccountType::where('code', 'OA')->firstOrFail();

        // The final workbook presents Shared Operating Cost (3000) as a
        // summary group, with Shared Expense (3100) and its leaves beneath it.
        $sharedOperatingCost = Account::updateOrCreate(
            ['company_id' => $company->id, 'code' => '3000'],
            [
                'account_type_id' => $type->id,
                'parent_id' => null,
                'name' => 'Shared Operating Cost',
                'description' => null,
                'is_control_account' => false,
                'is_active' => true,
            ],
        );

        $sharedExpense = Account::where('company_id', $company->id)
            ->where('code', '3100')
            ->first();

        if ($sharedExpense) {
            $sharedExpense->forceFill([
                'parent_id' => $sharedOperatingCost->id,
                'name' => 'Shared Expense',
            ])->save();

            Account::where('company_id', $company->id)
                ->whereIn('code', ['3101-01', '3101-02'])
                ->update(['parent_id' => $sharedExpense->id]);
        }

        // The former working-paper setup used branch-specific 3000 leaves;
        // those are replaced by the summary group in the final workbook.
        Account::where('company_id', $company->id)
            ->whereIn('code', ['3000-01', '3000-02'])
            ->update([
                'is_active' => false,
                'description' => 'LEGACY ACCOUNT. Replaced by the Shared Operating Cost summary group in the final workbook.',
            ]);
    }

    private function renameAccount($company, string $code, string $name): void
    {
        Account::where('company_id', $company->id)
            ->where('code', $code)
            ->update(['name' => $name]);
    }

    private function upsert($company, ?Account $parent, string $code, string $name, string $typeCode): Account
    {
        $type = AccountType::where('code', $typeCode)->firstOrFail();

        return Account::updateOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'account_type_id' => $type->id,
                'parent_id' => $parent?->id,
                'name' => $name,
                'description' => null,
                'is_control_account' => false,
                'is_active' => true,
            ],
        );
    }
}
