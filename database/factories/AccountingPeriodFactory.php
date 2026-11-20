<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = (int) fake()->unique()->numberBetween(1, 12);
        $month = now()->startOfMonth();

        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'period_number' => $number,
            'name' => 'Period '.$number,
            'start_date' => $month->copy()->startOfMonth()->toDateString(),
            'end_date' => $month->copy()->endOfMonth()->toDateString(),
            'status' => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'closed',
        ]);
    }
}
