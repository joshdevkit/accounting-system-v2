<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) fake()->numberBetween(2020, 2035);

        return [
            'company_id' => Company::factory(),
            'year' => $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'status' => 'open',
        ];
    }

    /**
     * A year that covers today, so posting rules are satisfied by default.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes): array => [
            'year' => (int) now()->format('Y'),
            'start_date' => now()->format('Y-01-01'),
            'end_date' => now()->format('Y-12-31'),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'closed',
        ]);
    }
}
