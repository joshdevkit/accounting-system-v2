<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountingCompanySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a configurable starter company and two branches.
     *
     * These values are intended for local development. Set the
     * ACCOUNTING_* environment variables to your actual company
     * information before running the seeder in a real environment.
     */
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['code' => env('ACCOUNTING_COMPANY_CODE', 'DEMO')],
            [
                'name' => env('ACCOUNTING_COMPANY_NAME', 'Demo Company'),
                'tax_identification_number' => env('ACCOUNTING_COMPANY_TIN'),
                'address' => env('ACCOUNTING_COMPANY_ADDRESS'),
                'phone' => env('ACCOUNTING_COMPANY_PHONE'),
                'email' => env('ACCOUNTING_COMPANY_EMAIL'),
                'is_active' => true,
            ],
        );

        $branches = [
            [
                'code' => env('ACCOUNTING_BRANCH_1_CODE', 'BR-001'),
                'name' => env('ACCOUNTING_BRANCH_1_NAME', 'Branch 1'),
            ],
            [
                'code' => env('ACCOUNTING_BRANCH_2_CODE', 'BR-002'),
                'name' => env('ACCOUNTING_BRANCH_2_NAME', 'Branch 2'),
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $branch['code'],
                ],
                [
                    'name' => $branch['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
