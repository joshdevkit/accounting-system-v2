<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Joshua Mendoza Pacho',
                'password' => 'admin123',
            ],
        );

        $roles = [
            'Administrator',
            'Accountant',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        $admin->syncRoles('Administrator');

        // $this->call([
        //     AccountingAccountTypeSeeder::class,
        //     AccountingCompanySeeder::class,
        //     ChartOfAccountsSeeder::class,
        //     IncomeStatementAccountsSeeder::class,
        //     AccountingFiscalYearSeeder::class,
        //     // AccountingDemoDataSeeder::class,
        // ]);
    }
}
