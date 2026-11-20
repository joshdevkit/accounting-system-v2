<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountingAccountTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the standard accounting account types.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'ASSET',
                'name' => 'Assets',
                'category' => 'asset',
                'normal_balance' => 'debit',
                'description' => 'Resources controlled by the company that are expected to provide future economic benefit.',
            ],
            [
                'code' => 'LIABILITY',
                'name' => 'Liabilities',
                'category' => 'liability',
                'normal_balance' => 'credit',
                'description' => 'Present obligations of the company arising from past events.',
            ],
            [
                'code' => 'EQUITY',
                'name' => 'Equity',
                'category' => 'equity',
                'normal_balance' => 'credit',
                'description' => 'Residual interest in the company after deducting liabilities from assets.',
            ],
            [
                'code' => 'REVENUE',
                'name' => 'Revenue',
                'category' => 'revenue',
                'normal_balance' => 'credit',
                'description' => 'Income earned from the company\'s ordinary and other operating activities.',
            ],
            [
                'code' => 'EXPENSE',
                'name' => 'Expenses',
                'category' => 'expense',
                'normal_balance' => 'debit',
                'description' => 'Costs incurred in generating revenue and operating the company.',
            ],
        ];

        foreach ($types as $type) {
            AccountType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'category' => $type['category'],
                    'normal_balance' => $type['normal_balance'],
                    'description' => $type['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
