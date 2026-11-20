<?php

namespace Database\Factories;

use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountType>
 */
class AccountTypeFactory extends Factory
{
    protected $model = AccountType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TYPE???')),
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']),
            'normal_balance' => fake()->randomElement(['debit', 'credit']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * A debit-normal asset type, which is what most tests post against.
     */
    public function asset(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'ASSET',
            'name' => 'Assets',
            'category' => 'asset',
            'normal_balance' => 'debit',
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'REVENUE',
            'name' => 'Revenue',
            'category' => 'revenue',
            'normal_balance' => 'credit',
        ]);
    }
}
