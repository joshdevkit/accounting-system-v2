<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'account_type_id' => AccountType::factory(),
            'parent_id' => null,
            'code' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->words(3, true),
            'description' => null,
            'is_control_account' => false,
            'is_active' => true,
        ];
    }

    /**
     * Accounts holding sub-accounts are summaries and cannot take postings,
     * so this state is used when testing that rule.
     */
    public function summary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_control_account' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
