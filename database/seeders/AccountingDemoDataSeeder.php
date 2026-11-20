<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\FiscalYearService;
use App\Services\JournalEntryService;
use App\Services\PosPostingService;
use App\Support\Money;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * A handful of realistic entries so the ledgers, dashboards and reports can be
 * clicked through straight after `migrate --seed`.
 *
 * Remove this seeder (or don't call it) for real data.
 */
class AccountingDemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $company = Company::where('code', env('ACCOUNTING_COMPANY_CODE', 'DEMO'))->first();

        if (! $company) {
            return;
        }

        if ($company->journalEntries()->exists()) {
            $this->command?->line('Demo ledger data already exists, skipping.');

            return;
        }

        $period = app(FiscalYearService::class)->currentPeriod($company);

        if (! $period || ! $period->allowsPosting()) {
            $this->command?->warn('No open accounting period for today, skipping demo entries.');

            return;
        }

        $user = User::query()->orderBy('id')->first();
        $date = $period->start_date->toDateString();

        $this->seedOpeningBalance($company, $user, $date);
        $this->seedPosDaySummary($company, $user, $period->start_date->copy()->addDays(2)->toDateString());
    }

    protected function seedOpeningBalance(Company $company, ?User $user, string $date): void
    {
        $cash = $this->account($company, '1120');
        $equity = $this->account($company, '3100');

        if (! $cash || ! $equity) {
            return;
        }

        app(JournalEntryService::class)->store($company, [
            'transaction_date' => $date,
            'description' => 'Owner contribution opening the business',
        ], [
            ['account_id' => $cash->id, 'debit' => 250000, 'credit' => 0, 'description' => 'Initial deposit'],
            ['account_id' => $equity->id, 'debit' => 0, 'credit' => 250000, 'description' => 'Capital'],
        ], $user, post: true);
    }

    protected function seedPosDaySummary(Company $company, ?User $user, string $date): void
    {
        $branch = $company->branches()->orderBy('id')->first();

        if (! $branch) {
            return;
        }

        $required = ['1110', '1130', '1140', '1210', '2210', '2500', '4130', '4900', '1150'];

        $present = Account::query()
            ->where('company_id', $company->id)
            ->whereIn('code', $required)
            ->pluck('code')
            ->all();

        if (array_diff($required, $present) !== []) {
            $this->command?->warn('Chart of accounts is missing POS posting accounts, skipping the demo day summary.');

            return;
        }

        $netSales = 48250.00;
        $vat = round($netSales * 0.12, 2);
        $serviceCharge = round($netSales * 0.10, 2);

        try {
            $pos = $company->posEntries()->create([
                'branch_id' => $branch->id,
                'business_date' => $date,
                'pos_reference' => 'Z-'.$date,
                'gross_sales' => Money::decimal(Money::cents($netSales) + 250000),
                'discount_amount' => 2500.00,
                'taxable_amount' => $netSales,
                'exempted_amount' => 0,
                'vat_amount' => $vat,
                'local_tax_amount' => 0,
                'service_charge_amount' => $serviceCharge,
                'net_sales' => $netSales,
                // Tendered amounts tie to net sales + VAT + service charge.
                'cash_amount' => 26000.00,
                'card_amount' => 14500.00,
                'gcash_qrph_amount' => 8365.00,
                'room_post_amount' => 10000.00,
                'status' => 'draft',
                'created_by' => $user?->id,
                'notes' => 'Demo day summary: restaurant and room service.',
            ]);

            $entry = app(PosPostingService::class)->post($pos, $user);

            $this->command?->info("Demo POS summary posted as {$entry->reference_no}.");
        } catch (ValidationException $exception) {
            $this->command?->warn('Demo POS summary could not be posted: '.$exception->getMessage());
        }
    }

    protected function account(Company $company, string $code): ?Account
    {
        return Account::query()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->first();
    }
}
