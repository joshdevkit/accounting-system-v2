<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PosEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosEntry>
 */
class PosEntryFactory extends Factory
{
    protected $model = PosEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $netSales = fake()->randomFloat(2, 1000, 50000);
        $vat = round($netSales * 0.12, 2);

        return [
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'journal_entry_id' => null,
            'business_date' => now()->toDateString(),
            'pos_reference' => 'Z-'.fake()->unique()->numerify('######'),
            'gross_sales' => $netSales,
            'discount_amount' => 0,
            'taxable_amount' => $netSales,
            'exempted_amount' => 0,
            'vat_amount' => $vat,
            'local_tax_amount' => 0,
            'service_charge_amount' => 0,
            'net_sales' => $netSales,
            'cash_amount' => $netSales + $vat,
            'card_amount' => 0,
            'gcash_qrph_amount' => 0,
            'bank_transfer_amount' => 0,
            'cheque_amount' => 0,
            'accounts_receivable_amount' => 0,
            'room_post_amount' => 0,
            'gift_voucher_amount' => 0,
            'other_payment_amount' => 0,
            'status' => 'draft',
            'notes' => null,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $company->id,
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'voided',
            'voided_at' => now(),
        ]);
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'posted',
            'posted_at' => now(),
        ]);
    }
}
