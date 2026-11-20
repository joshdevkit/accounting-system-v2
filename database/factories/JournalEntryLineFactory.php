<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntryLine>
 */
class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 10000);

        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'debit' => $amount,
            'credit' => 0,
            'description' => null,
        ];
    }

    public function credit(float $amount): static
    {
        return $this->state(fn (array $attributes): array => [
            'debit' => 0,
            'credit' => $amount,
        ]);
    }

    public function debit(float $amount): static
    {
        return $this->state(fn (array $attributes): array => [
            'debit' => $amount,
            'credit' => 0,
        ]);
    }
}
