<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    /**
     * Journal entries are normally written by JournalEntryService, which
     * derives the fiscal year and period from the transaction date. This
     * factory exists for read-only screens; pass a period (or use
     * inPeriod()) so the entry lines up with a real fiscal year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'branch_id' => null,
            'fiscal_year_id' => FiscalYear::factory(),
            'accounting_period_id' => AccountingPeriod::factory(),
            'reference_no' => 'JE-'.fake()->unique()->numerify('######'),
            'transaction_date' => now()->toDateString(),
            'description' => rtrim(fake()->sentence(6), '.'),
            'source_type' => 'manual',
            'source_reference' => null,
            'status' => 'draft',
        ];
    }

    public function inPeriod(AccountingPeriod $period): static
    {
        $period->loadMissing('fiscalYear');

        return $this->state(fn (array $attributes): array => [
            'company_id' => $period->fiscalYear->company_id,
            'fiscal_year_id' => $period->fiscal_year_id,
            'accounting_period_id' => $period->id,
            'transaction_date' => $period->start_date->toDateString(),
        ]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $company->id,
        ]);
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'posted',
            'posted_at' => now(),
        ]);
    }

    public function reversed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'reversed',
            'posted_at' => now(),
        ]);
    }

    public function fromPos(string $reference = 'Z-1'): static
    {
        return $this->state(fn (array $attributes): array => [
            'source_type' => 'pos',
            'source_reference' => $reference,
            'status' => 'posted',
            'posted_at' => now(),
        ]);
    }
}
